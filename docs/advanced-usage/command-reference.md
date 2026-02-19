---
title: Command reference
weight: 4
---

Every endpoint command supports these universal options:

| Option | Description |
|--------|-------------|
| `--field=key=value` | Send a form field (repeatable) |
| `--input=JSON` | Send raw JSON body |
| `--json` | Output raw JSON instead of human-readable format |
| `--yaml` | Output as YAML |
| `--minify` | Minify JSON output (implies `--json`) |
| `-H`, `--headers` | Include response headers in output |
| `--output-html` | Show the full response body when content-type is text/html |

Path and query parameter options are generated from the spec and shown in each command's `--help` output.
