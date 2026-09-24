// @minecraft/server-admin: no server variables or secrets are configured on Amber.
export const variables = { names: [], get(){ return undefined; } };
export const secrets = { names: [], get(){ return undefined; } };
export class SecretString{ constructor(){} }
export function transferPlayer(){ throw new Error("transferPlayer is not supported"); }

// every other name the game's module exports, so any pack's imports link (ours above take precedence)
export * from "./server-admin-names.mjs";
