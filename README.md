# Symfony Profiler MCP Bundle

Expose **Symfony Web Profiler** metrics, bottleneck diagnostics, and before/after performance comparisons directly to AI coding agents (such as **Claude Code** or **Cursor**) via the **Model Context Protocol (MCP)**.

Instead of manually copying SQL queries, memory stats, or controller paths from the browser toolbar, your AI agent can inspect the latest profiler session, pinpoint the exact bottleneck in your PHP code, apply a fix, and verify the improvement on its own.

## Key Features

- **Token-Efficient Profiler Audits (`profiler_audit_latest`)**: Extracts and aggregates key metrics from Symfony collectors (`time`, `memory`, `db`, `twig`, `cache`, `http_client`) without flooding the LLM context with raw `VarDumper` dumps.
- **Noise Filtering**: Automatically skips internal requests (`/_wdt`, `/_profiler`, `/favicon.ico`, `OPTIONS`) to target the actual application request you just triggered.
- **Exact Code Mapping**: Unpacks `RequestDataCollector` to provide the exact controller class, method, file path, and line number.
- **Automated Bottleneck Detection**: Flags slow response times (`> 300 ms`), high peak memory usage (`> 64 MB`), slow Twig rendering (`> 100 ms`), cache misses, and groups repeated Doctrine SQL patterns to highlight **N+1 query** candidates.
- **Before/After Verification (`profiler_compare`)**: Compares two profiler tokens and calculates exact deltas and percentage changes across execution time, memory, SQL queries, and resolved N+1 patterns.
- **Dev-Only & Ecosystem Ready**: Automatically registers services only in the `dev` environment and supports both standalone `symfony/mcp-bundle` (`stdio`) and `symfony/ai-mate`.

---

## Installation (In Your Symfony Project)

1. Install the bundle as a dev dependency:

```bash
composer require --dev lukaszkrakowiak/symfony-profiler-mcp-bundle
```

2. Enable the `stdio` transport in `config/packages/mcp.yaml`:

```yaml
when@dev:
  mcp:
    app: 'symfony-app'
    version: '0.1.0'
    client_transports:
      stdio: true
```

3. Register the MCP server in your project's `.mcp.json` (for Claude Code / Cursor):

```json
{
  "mcpServers": {
    "symfony-profiler": {
      "command": "php",
      "args": ["bin/console", "mcp:server"]
    }
  }
}
```

4. *(Optional)* Add workflow instructions to your project's `CLAUDE.md` so the agent closes the optimization loop automatically:

```markdown
# Performance & Profiler Workflow
When asked to analyze performance, inspect the last profiler session, or optimize an endpoint:
1. Call `profiler_audit_latest` to inspect the latest HTTP request, bottlenecks, and controller file location.
2. Refactor the inefficient PHP code.
3. Trigger the endpoint again (e.g., via `curl`) to capture a fresh profiler token.
4. Call `profiler_compare` with the old and new tokens to verify the exact improvement.
```

---

## Available MCP Tools

### `profiler_audit_latest`
Finds the most recent application HTTP request (or loads a specific token) and returns a structured performance audit.

**Parameters:**
- `urlContains` (`string|null`, optional): Filter recent requests by a URL substring (e.g., `"/products"`).
- `token` (`string|null`, optional): Inspect a specific profiler token instead of the latest one.

**Example Output:**
```json
{
  "token": "a1b2c3",
  "profiler_url": "http://localhost:8000/_profiler/a1b2c3",
  "method": "GET",
  "url": "[http://127.0.0.1:8000/products](http://127.0.0.1:8000/products)",
  "status_code": 200,
  "controller": {
    "route": "app_products",
    "callable": "App\\Controller\\CatalogController::list",
    "class": "App\\Controller\\CatalogController",
    "method": "list",
    "file": "/app/demo/src/Controller/CatalogController.php",
    "line": 14
  },
  "detected_bottlenecks": [
    "Slow response time: 442.18 ms",
    "High memory usage: 78.50 MB"
  ],
  "metrics": {
    "total_time_ms": 442.18,
    "peak_memory_mb": 78.5,
    "cache": {
      "reads": 35,
      "hits": 0,
      "misses": 35
    }
  }
}
```

### `profiler_compare`
Compares two profiler sessions (`beforeToken` and `afterToken`) to validate optimizations.

**Parameters:**
- `beforeToken` (`string`, required): Profiler token captured before the code change.
- `afterToken` (`string`, required): Profiler token captured after the code change.

**Example Output:**
```json
{
  "before": { "token": "a1b2c3", "url": "[http://127.0.0.1:8000/products](http://127.0.0.1:8000/products)", "status_code": 200 },
  "after": { "token": "d4e5f6", "url": "[http://127.0.0.1:8000/products](http://127.0.0.1:8000/products)", "status_code": 200 },
  "diff": {
    "total_time_ms": { "before": 442.18, "after": 6.12, "delta": -436.06, "change_percent": -98.6 },
    "peak_memory_mb": { "before": 78.5, "after": 6.25, "delta": -72.25, "change_percent": -92.0 },
    "db_query_count": null,
    "db_time_ms": null
  },
  "n_plus_one_diff": {
    "resolved_queries": [],
    "remaining_queries": []
  }
}
```

---

## Quickstart: Running the Demo Application (`demo/`)

This repository includes a zero-configuration Symfony demo application in `demo/` (no database or Docker required) featuring an intentionally unoptimized `/products` endpoint (`CatalogController`) with three performance bottlenecks:
1. **Excessive memory allocation (`> 64 MB`)** via redundant temporary buffers.
2. **$O(N^2)$ nested loop lookup** instead of an indexed lookup map.
3. **Cache key busting** (`microtime()` in the cache key causing 100% cache misses and `> 400 ms` latency).

### 1. Install dependencies

```bash
cd demo
composer install
```

### 2. Start the local PHP server

```bash
php -S 127.0.0.1:8000 -t public
```

### 3. Generate a slow profiler session

In a second terminal:

```bash
curl -s [http://127.0.0.1:8000/products](http://127.0.0.1:8000/products) > /dev/null
```

### 4. Run Claude Code and optimize

From inside the `demo/` directory (which already includes `.mcp.json` and `CLAUDE.md`):

```bash
claude
```

Ask Claude:
> *"Check the latest profiler session, identify the performance bottlenecks, fix the code, and compare the before and after metrics."*

---

## License

MIT License. See [LICENSE](LICENSE) for details.
