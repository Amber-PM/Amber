// @minecraft/math: vector helpers.
export class Vector3Utils{
	static equals(a, b){ return a.x === b.x && a.y === b.y && a.z === b.z; }
	static add(a, b){ return { x: a.x + (b.x ?? 0), y: a.y + (b.y ?? 0), z: a.z + (b.z ?? 0) }; }
	static subtract(a, b){ return { x: a.x - (b.x ?? 0), y: a.y - (b.y ?? 0), z: a.z - (b.z ?? 0) }; }
	static scale(a, s){ return { x: a.x * s, y: a.y * s, z: a.z * s }; }
	static dot(a, b){ return a.x * b.x + a.y * b.y + a.z * b.z; }
	static cross(a, b){ return { x: a.y * b.z - a.z * b.y, y: a.z * b.x - a.x * b.z, z: a.x * b.y - a.y * b.x }; }
	static magnitude(a){ return Math.hypot(a.x, a.y, a.z); }
	static distance(a, b){ return Vector3Utils.magnitude(Vector3Utils.subtract(a, b)); }
	static normalize(a){ const m = Vector3Utils.magnitude(a) || 1; return { x: a.x / m, y: a.y / m, z: a.z / m }; }
	static floor(a){ return { x: Math.floor(a.x), y: Math.floor(a.y), z: Math.floor(a.z) }; }
	static clamp(a, l){ const c = (v, min, max) => Math.min(max ?? Infinity, Math.max(min ?? -Infinity, v)); return { x: c(a.x, l?.min?.x, l?.max?.x), y: c(a.y, l?.min?.y, l?.max?.y), z: c(a.z, l?.min?.z, l?.max?.z) }; }
	static lerp(a, b, t){ return { x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t, z: a.z + (b.z - a.z) * t }; }
	static slerp(a, b, t){ return Vector3Utils.lerp(a, b, t); }
	static toString(a, o){ const d = o?.decimals ?? 2, s = o?.delimiter ?? ", "; return [a.x, a.y, a.z].map(v => v.toFixed(d)).join(s); }
	static multiply(a, b){ return { x: a.x * b.x, y: a.y * b.y, z: a.z * b.z }; }
	static rotateX(v, a){ const c = Math.cos(a), s = Math.sin(a); return { x: v.x, y: v.y * c - v.z * s, z: v.y * s + v.z * c }; }
	static rotateY(v, a){ const c = Math.cos(a), s = Math.sin(a); return { x: v.x * c + v.z * s, y: v.y, z: -v.x * s + v.z * c }; }
	static rotateZ(v, a){ const c = Math.cos(a), s = Math.sin(a); return { x: v.x * c - v.y * s, y: v.x * s + v.y * c, z: v.z }; }
}
export class Vector3Builder{
	constructor(a, y, z){ if(typeof a === "object"){ this.x = a.x; this.y = a.y; this.z = a.z; } else { this.x = a; this.y = y; this.z = z; } }
	assign(v){ this.x = v.x; this.y = v.y; this.z = v.z; return this; }
	add(v){ return this.assign(Vector3Utils.add(this, v)); }
	subtract(v){ return this.assign(Vector3Utils.subtract(this, v)); }
	scale(s){ return this.assign(Vector3Utils.scale(this, s)); }
	normalize(){ return this.assign(Vector3Utils.normalize(this)); }
	floor(){ return this.assign(Vector3Utils.floor(this)); }
	dot(v){ return Vector3Utils.dot(this, v); }
	magnitude(){ return Vector3Utils.magnitude(this); }
	distance(v){ return Vector3Utils.distance(this, v); }
	equals(v){ return Vector3Utils.equals(this, v); }
	toString(o){ return Vector3Utils.toString(this, o); }
}
export const VECTOR3_ZERO = { x: 0, y: 0, z: 0 };
export const VECTOR3_UP = { x: 0, y: 1, z: 0 }, VECTOR3_DOWN = { x: 0, y: -1, z: 0 }, VECTOR3_ONE = { x: 1, y: 1, z: 1 },
	VECTOR3_FORWARD = { x: 0, y: 0, z: 1 }, VECTOR3_BACK = { x: 0, y: 0, z: -1 }, VECTOR3_LEFT = { x: -1, y: 0, z: 0 }, VECTOR3_RIGHT = { x: 1, y: 0, z: 0 };
export const clampNumber = (v, min, max) => Math.min(max, Math.max(min, v));
