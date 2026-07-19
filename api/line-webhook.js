export default async function handler(req, res) {
  console.log("LINE Webhook:", req.body);
  res.status(200).send("OK");
}
