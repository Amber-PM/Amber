// @minecraft/common
export class ArgumentOutOfBoundsError extends Error{}
export class EngineError extends Error{}
export class InvalidArgumentError extends Error{}
export class PropertyOutOfBoundsError extends Error{}
export class UnsupportedFunctionalityError extends Error{}

// every other name the game's module exports, so any pack's imports link (ours above take precedence)
export * from "./common-names.mjs";
