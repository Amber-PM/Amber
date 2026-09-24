// @minecraft/vanilla-data: identifier enums. Keys are PascalCase, values the namespaced ids.
const snake = (k) => "minecraft:" + String(k).replace(/([a-z0-9])([A-Z])/g, "$1_$2").replace(/([A-Z])([A-Z][a-z])/g, "$1_$2").toLowerCase();
const ids = () => new Proxy({}, { get: (t, k) => typeof k === "string" ? snake(k) : undefined });
export const MinecraftBlockTypes = ids(), MinecraftItemTypes = ids(), MinecraftEntityTypes = ids(), MinecraftEffectTypes = ids(),
	MinecraftEnchantmentTypes = ids(), MinecraftBiomeTypes = ids(), MinecraftCameraPresetsTypes = ids(), MinecraftFeatureTypes = ids(),
	MinecraftCooldownCategoryTypes = ids(), MinecraftPotionEffectTypes = ids(), MinecraftPotionLiquidTypes = ids(), MinecraftPotionModifierTypes = ids();
export const MinecraftDimensionTypes = Object.freeze({ Overworld: "minecraft:overworld", Nether: "minecraft:nether", TheEnd: "minecraft:the_end" });
