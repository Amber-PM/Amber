// Modules the server does not provide. Importing works; using them throws.
const fail = () => { throw new Error("This @minecraft module is not available on this server"); };
export const register = fail, registerAsync = fail, world = undefined, http = undefined, HttpRequest = fail, HttpHeader = fail, HttpRequestMethod = {}, GameTest = undefined, Test = undefined, SimulatedPlayer = undefined;
export default {};
