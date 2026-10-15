import { app } from "./app";
import { env } from "./config/env";
import { expireUnpaid } from "./modules/commerce";

app.listen(env.port, () => console.log(`Rumah Tani API on :${env.port}`));
setInterval(() => expireUnpaid().catch(e => console.error("expireUnpaid", e)), 60_000);
