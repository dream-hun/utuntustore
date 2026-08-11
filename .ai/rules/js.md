---
paths:
  - 'resources/js/**'
---

# Js

## shadcn add: use npm, not the detected pnpm
`npx shadcn@latest add ...` fails with `spawn pnpm ENOENT`. A `pnpm-workspace.yaml` exists at the root so shadcn picks pnpm, but pnpm is not installed and the project actually uses npm (package-lock.json).

Workaround: install the deps first with npm, then temporarily move `pnpm-workspace.yaml` aside, run the shadcn add, and move it back. Either delete the stale workspace file or install pnpm to fix this permanently.
