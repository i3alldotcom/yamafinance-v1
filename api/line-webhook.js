export default async function handler(req, res) {
  console.log("LINE Webhook:", req.body);

  // ส่งข้อมูลไป NAS
  await fetch("http://dmcyamanashi.myqnapcloud.com/yamafinance/public/api/line.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ test: "hello from vercel" })
  });

  res.status(200).send("OK");
}
