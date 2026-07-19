export default async function handler(req, res) {
  try {
    // ส่งข้อมูลจริงจาก LINE ไป NAS
    await fetch("http://dmcyamanashi.myqnapcloud.com/yamafinance/public/api/line.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-API-KEY": "Yama072+Finance@2026"
      },
      body: JSON.stringify(req.body)
    });

    res.status(200).send("OK");
  } catch (error) {
    console.error("Error:", error);
    res.status(500).send("Error");
  }
}
