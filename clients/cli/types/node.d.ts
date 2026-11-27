// Minimal ambient typings for the few Node.js APIs the CLI uses. They stand in for
// `@types/node` so the CLI adds no dependency; replace this file with `@types/node`
// once `security` has approved it. The `pidgey-client` declarations are not declared
// here: the library ships its own (`dist/lib/index.d.ts`).

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
  env: Record<string, string | undefined>;
  exitCode: number | undefined;
  platform: string;
  stdin: { isTTY?: boolean };
  once(event: string, listener: () => void): unknown;
  stdout: { write(text: string): unknown };
  stderr: { write(text: string): unknown };
};
