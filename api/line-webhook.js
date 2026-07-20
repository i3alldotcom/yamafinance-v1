export default async function handler(req, res) {
  // parse a Response as JSON without ever throwing on empty/non-JSON bodies
  async function safeJson(response, label) {
    const text = await response.text();
    if (!text) {
      console.error(`${label}: empty body (status ${response.status})`);
      return null;
    }
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error(`${label}: non-JSON body (status ${response.status}):`, text.slice(0, 300));
      return null;
    }

const visionData = await visionRes.json();   // if this throws...
...
body.imageBase64 = base64;                    // ...THIS never runs

Because body.imageBase64 = base64; is at the end of the try, any OCR/Gemini failure means the image is
never attached to body. So the bill iAS (or forces line.php into itsfallback re-download path), and ocrText is missing. One empty response kills the whole bill.

Two more concrete defects

1. Malformed Gemini URL — in what you pasted there are stray spaces:
.../gemini-2.0-flash:generateContent  ?key=${geminiKey}
1. The generateContent  ?key= with spk your real file; if the spaces arethere, that request never succeeds.
2. visionRes.json() / gemRes.json() are parsed unconditionally regardless of .ok.

Fix

Attach the image first, and parse every response defensively. Here's the corrected handler:

export default async function handler(req, res) {
  // parse a Response as JSON withoutJSON bodies
  async function safeJson(response, label) {
    const text = await response.text();
    if (!text) {
      console.error(`${label}: empty body (status ${response.status})`);
      return null;
    }
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error(`${label}: non-JSON body (status ${response.status}):`, text.slice(0, 300));
      return null;
    }
  }

  try {
    const body = req.body;
    const event = body && body.events ? body.events[0] : null;
    const message = event ? event.mes

    if (message && message.type === 'image') {
      const token = process.env.LINE_CHANNEL_ACCESS_TOKEN;
      const visionKey = process.env.G
      const geminiKey = process.env.GEMINI_API_KEY;

      try {
        const msgRes = await fetch(
          `https://api-data.line.me/v2/bot/message/${message.id}/content`,
          { headers: { Authorization:
        );

        if (msgRes.ok) {
          const buf = Buffer.from(await msgRes.arrayBuffer());
          const base64 = buf.toString

          // ✅ attach the image IMMEver loses the bill
          body.imageBase64 = base64;

          // 1) OCR with Vision
          let jpText = '';
          const visionRes = await fetch(
            `https://vision.googleapis.com/v1/images:annotate?key=${visionKey}`,
            {
              method: 'POST',
              headers: { 'Content-Typ
              body: JSON.stringify({
                requests: [{ image: {: [{ type: 'DOCUMENT_TEXT_DETECTION'}] }]
              })
            }
          );
          const visionData = await sa
          if (!visionRes.ok || !visionData) {
            console.error('Vision API error', visionRes.status);
          } else {
            const ann = visionData.responses &&
                        visionData.responses[0] &&
                        visionData.ren;
            jpText = ann ? ann.text : '';
          }

          body.ocrText = jpText; // sensible default; may be overwritten by Gemini below

          // 2) Translate + summarize
          if (jpText && geminiKey) {
            const prompt = `คุณคือผู้ช่วยบัหรือภาษาอื่นที่ไม่ใช่ภาษาไทยที่อ่านด้วย OCRกรุณาตอบกลับเฉพาะ JSON:
{"thai_text":"แปลภาษาไทยทั้งหมด แต่ถ้าบิลเป็นภาษาไทยอยู่แล้วให้สรุปได้เลย":"สรุปสั้นๆ บิลนี้คือค่าอะไร (เช่นค่าไฟฟ้า, ค่าน้ำ, อินเทอร์เน็ต)","amount":"จำนวนเงินตัวเลขอย่างเดียว ถ้าไม่มีให้ว่าง","bill_date":"วันที่ในบิล YYYY-MM-DD ถ้าไม่มีให้ว่าง"}
โดยไม่ต้องอธิบายเพิ่ม:\n\n${jpText}`;

            const gemRes = await fetch(
              `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${geminiKey}`,
              {
                method: 'POST',
                headers: { 'Content-T
                body: JSON.stringify({
                  contents: [{ parts:
                  generationConfig: { responseMimeType: 'application/json' }
                })
              }
            );
            const gemData = await safeJson(gemRes, 'Gemini');
            let gem = null;
            if (gemRes.ok && gemData)
              try {
                const txt = gemData.c
                            gemData.candidates[0] &&
                            gemData.c].text;
                gem = txt ? JSON.parse(txt) : null;
              } catch (e) { console.e e); }
            }
            if (gem) {
              body.ocrText     = gem.
              body.billSummary = gem.category_th || '';
              body.amount      = gem.amount || '';
              body.billDate    = gem.bill_date || '';
            }
          }
        } else {
          console.error('LINE downloa
        }
      } catch (e) {
        console.error('OCR/Line failed:', e);
        // image already attached above (if download succeeded), so NAS still gets the bill
      }
    }

    const nasRes = await fetch(
      "http://dmcyamanashi.myqnapcloud.com/yamafinance/public/api/line.php",
      {
        method: "POST",
        headers: { "Content-Type": "a": "Yama072+Finance@2026" },
        body: JSON.stringify(body)
      }
    );
    if (!nasRes.ok) { res.status(500).send("NAS failed"); return; }
    res.status(200).send("OK");
  } catch (error) {
    console.error("Error:", error);
    res.status(500).send("Error");
  }
}
