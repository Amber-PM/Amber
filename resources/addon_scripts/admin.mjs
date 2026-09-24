// @minecraft/server-admin: no server variables or secrets are configured on Amber.
export const variables = { names: [], get(){ return undefined; } };
export const secrets = { names: [], get(){ return undefined; } };
export class SecretString{ constructor(){} }
export function transferPlayer(){ throw new Error("transferPlayer is not supported"); }
