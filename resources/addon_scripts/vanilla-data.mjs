// @minecraft/vanilla-data: the game's identifiers, member for member (generated from its vanilla data).
export * from "./vanilla-data-names.mjs";

// names older versions of the package had; any member resolves to its namespaced id
const snake = (k) => "minecraft:" + String(k).replace(/([a-z0-9])([A-Z])/g, "$1_$2").replace(/([A-Z])([A-Z][a-z])/g, "$1_$2").toLowerCase();
const ids = () => new Proxy({}, { get: (t, k) => typeof k === "string" ? snake(k) : undefined });
export const MinecraftPotionLiquidTypes = ids(), MinecraftPotionModifierTypes = ids();
