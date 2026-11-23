// Minimal ambient typings for the few Node.js APIs the CLI uses. They stand in for
// `@types/node` so the CLI adds no dependency; replace this file with `@types/node`
// once `security` has approved it. `pidgey-client` ships no typings yet (#250), so its
// public export used here is declared as well.

declare module 'pidgey-client' {
  export const VERSION: string;
}

declare module 'node:util' {
  export function parseArgs(config: {
    args?: string[];
    options?: Record<string, { type: 'boolean' | 'string'; multiple?: boolean }>;
    strict?: boolean;
    allowPositionals?: boolean;
    tokens?: boolean;
  }): {
    values: Record<string, unknown>;
    positionals: string[];
    tokens: Array<{ kind: string; name: string }>;
  };
}

declare const process: {
  argv: string[];
  exitCode: number | undefined;
  stdout: { write(text: string): unknown };
  stderr: { write(text: string): unknown };
};
