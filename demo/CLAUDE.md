# Performance & Profiler Workflow
When asked to analyze performance, inspect the last profiler session, or optimize an endpoint:
1. Call the `profiler_audit_latest` MCP tool to inspect the latest HTTP request, SQL queries, and controller location.
2. Fix the detected bottlenecks in the PHP code.
3. Trigger the endpoint again (e.g., via `curl -s http://127.0.0.1:8000/products > /dev/null`) to generate a fresh profiler token.
4. Call `profiler_compare` with the old and new tokens to verify the exact improvement.
