import crypto from "crypto";
import { env } from "../config/env";

export interface ChargeResult { gatewayRef: string; payCode: string }

/** Payment gateway adapter. "mock" works offline; "midtrans" uses the Core API (bank transfer/VA & e-wallet). */
export async function createCharge(p: { orderRef: string; amount: number; method: string; customerEmail: string }): Promise<ChargeResult> {
  if (env.paymentProvider === "midtrans") {
    const body: any = { transaction_details: { order_id: p.orderRef, gross_amount: Math.round(p.amount) }, customer_details: { email: p.customerEmail } };
    if (p.method.startsWith("va_")) { body.payment_type = "bank_transfer"; body.bank_transfer = { bank: p.method.slice(3) }; }
    else if (p.method === "gopay") body.payment_type = "gopay";
    else throw new Error("Unsupported method for midtrans: " + p.method);
    const r = await fetch("https://api.sandbox.midtrans.com/v2/charge", {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json",
        Authorization: "Basic " + Buffer.from(env.midtransKey + ":").toString("base64") },
      body: JSON.stringify(body),
    });
    const j: any = await r.json();
    if (!r.ok || !["200", "201"].includes(String(j.status_code))) throw new Error("Gateway error: " + (j.status_message ?? r.status));
    const code = j.va_numbers?.[0]?.va_number ?? j.actions?.find((a: any) => a.name === "deeplink-redirect")?.url ?? "";
    return { gatewayRef: p.orderRef, payCode: code };
  }
  // mock
  const code = p.method.startsWith("va_") ? "88" + Math.floor(1e10 * Math.random()).toString().padStart(10, "0")
    : p.method === "bank_transfer" ? "REK-1234567890 a.n. Rumah Tani" : `mock://pay/${p.orderRef}`;
  return { gatewayRef: p.orderRef, payCode: code };
}

/** HMAC signature (mock). Midtrans uses SHA512(order_id+status_code+gross_amount+serverKey). */
export function verifyWebhook(body: any, headerSig?: string): boolean {
  if (env.paymentProvider === "midtrans") {
    const exp = crypto.createHash("sha512")
      .update(`${body.order_id}${body.status_code}${body.gross_amount}${env.midtransKey}`).digest("hex");
    return safeEq(exp, body.signature_key ?? "");
  }
  const exp = crypto.createHmac("sha256", env.webhookSecret).update(`${body.order_id}|${body.status}|${body.amount}`).digest("hex");
  return safeEq(exp, headerSig ?? "");
}
export const signMock = (orderId: string, status: string, amount: number) =>
  crypto.createHmac("sha256", env.webhookSecret).update(`${orderId}|${status}|${amount}`).digest("hex");

function safeEq(a: string, b: string) {
  const x = Buffer.from(a), y = Buffer.from(b);
  return x.length === y.length && crypto.timingSafeEqual(x, y);
}
