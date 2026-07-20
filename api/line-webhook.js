module.exports = async function handler(req, res) {
  // อ่านคำตอบเป็น JSON โดยไม่พังเวลาคำตอบว่าง/ไม่ใช่ JSON
  async function safeJson(response, label) {
    const text = await response.text();
    if (!text) {
      console.error(label + ': empty body (status ' + response.status + ')');
      return null;
    }
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error(label + ': non-J.status + '):', text.slice(0, 300));
      return null;
    }
  }

  try {
    const body = req.body;
    const event = body && body.events ? body.events[0] : null;
    const message = event ? event.me

    if (message && message.type ===
      const token = process.env.LINE_CHANNEL_ACCESS_TOKEN;
      const visionKey = process.env.GOOGLE_VISION_API_KEY;
      const geminiKey = process.env.

      try {
        const msgRes = await fetch(
          'https://api-data.line.me/v2/bot/message/' + message.id + '/content',
          { headers: { Authorization: 'Bearer ' + token } }
        );

        if (msgRes.ok) {
          const buf = Buffer.from(await msgRes.arrayBuffer());
          const base64 = buf.toStrin

          // ✅ แนบรูปทันที (ย้ายขึ้นมาแล้ว
          body.imageBase64 = base64;

          // 1) OCR ด้วย Vision
          let jpText = '';
          const visionRes = await fe
            'https://vision.googleapis.com/v1/images:annotate?key=' + visionKey,
            {
              method: 'POST',
              headers: { 'Content-Ty
              body: JSON.stringify({
                requests: [{ image: : [{ type: 'DOCUMENT_TEXT_DETECTION'}] }]
              })
            }
          );
          const visionData = await s
          if (!visionRes.ok || !visionData) {
            console.error('Vision API error', visionRes.status);
          } else {
            const ann = visionData.r
                        visionData.responses[0] &&
                        visionData.rn;
            jpText = ann ? ann.text : '';
          }

          body.ocrText = jpText; //  ด้านล่าง

          // 2) แปลไทย + สรุป ด้วย Gemini
          if (jpText && geminiKey) {
            const prompt = 'คุณคือผู้ช่วยหรือภาษาอื่นที่ไม่ใช่ภาษาไทยที่อ่านด้วย OCRกรุณาตอบกลับเฉพาะ JSON:\n{"thai_text":"แปลภาษาไทยทั้งหมด
แต่ถ้าบิลเป็นภาษาไทยอยู่แล้วให้สรุปได้เลย","caร (เช่น ค่าไฟฟ้า, ค่าน้ำ,อินเทอร์เน็ต)","amount":"จำนวนเงินตัวเลขอย่างเดียว ถ้าไม่มีให้ว่าง","bill_date":"วันที่ในบิล YYYY-MM-DD
ถ้าไม่มีให้ว่าง"}\nโดยไม่ต้องอธิบายเพิ่ม:\n\n'

            const gemRes = await fetch(
'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' + geminiKey,
              {
                method: 'POST',
                headers: { 'Content-
                body: JSON.stringify({
                  contents: [{ parts
                  generationConfig: { responseMimeType: 'application/json' }
                })
              }
            );
            const gemData = await sa
            let gem = null;
            if (gemRes.ok && gemData) {
              try {
                const txt = gemData.candidates &&
                            gemData.candidates[0] &&
                            gemData.candidates[0].content.parts[0].text;
                gem = txt ? JSON.parse(txt) : null;
              } catch (e) { console. e); }
            }
            if (gem) {
              body.ocrText     = gem.thai_text || jpText;
              body.billSummary = gem
              body.amount      = gem.amount || '';
              body.billDate    = gem.bill_date || '';
            }
          }
        } else {
          console.error('LINE download not ok', msgRes.status);
        }
      } catch (e) {
        console.error('OCR/Line fail
        // รูปแนบไปแล้วด้านบน (ถ้าโหลดสำเร็จ) NAS ยังได้บิลอยู่
      }
    }

    const nasRes = await fetch(
      'http://dmcyamanashi.myqnapcloud.com/yamafinance/public/api/line.php',
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-API-KEY': 'Yama072+Finance@2026' },
        body: JSON.stringify(body)
      }
    );
    if (!nasRes.ok) { res.status(500).send('NAS failed'); return; }
    res.status(200).send('OK');
  } catch (error) {
    console.error('Error:', error);
    res.status(500).send('Error');
  }
}
