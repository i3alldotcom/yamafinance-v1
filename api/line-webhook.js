export default async function handler(req, res) {
  console.log("LINE Webhook:", req.body);

  // ส่งข้อมูลไป NAS
  await fetch("https://dmcyamanashi.myqnapcloud.com/api/line.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
    "X-API-KEY": "Yama072+Finance@2026"
  },
  body: JSON.stringify(req.body)
});

  res.status(200).send("OK");
}
