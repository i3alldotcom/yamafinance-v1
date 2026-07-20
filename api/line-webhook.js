export default async function handler(req, res) {
    try {      const body = req.body;
      const event = body && body.events ? body.events[0] : null;
      const message = event ? event.message : null;

      if (message && message.type === 'image') {
        const token = process.env.LINE_CHANNEL_ACCESS_TOKEN;
        const visionKey = process.env.GOOGLE_VISION_API_KEY;
        const geminiKey = process.env.GEMINI_API_KEY;        try {
          const msgRes = await fetch(
            `https://api-data.line.me/v2/bot/message/${message.id}/content`,
            { headers: { Authorization: `Bearer ${token}` } }
          );
          if (msgRes.ok) {
            const buf = Buffer.from(await msgRes.arrayBuffer());
            const base64 = buf.toString('base64');

            // 1) OCR ด้วย Vision
            const visionRes = await fetch(
              `https://vision.googleapis.com/v1/images:annotate?key=${visionKey}`,
              {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  requests: [{ image: { content: base64 }, features: [{ type: 'DOCUMENT_TEXT_DETECTION'
  }] }]
                })
              }
            );
            const visionData = await visionRes.json();
            let jpText = '';
            if (!visionRes.ok) {
              console.error('Vision API error', visionRes.status, JSON.stringify(visionData));
            } else {
              const ann = visionData.responses && visionData.responses[0] &&
  visionData.responses[0].fullTextAnnotation;
              jpText = ann ? ann.text : '';
            }

            // 2) แปลไทย + สรุป ด้วย Gemini
            if (jpText && geminiKey) {
              const prompt = `คุณคือผู้ช่วยบัญชี ต่อไปนี้คือข้อความจากบิลภาษาญี่ปุ่นหรือภาษาอื่นที่ไม่ใช่ภาษาไทยที่อ่านด้วย OCR กรุณาตอบกลับเฉพาะ JSON:
  {"thai_text":"แปลภาษาไทยทั้งหมด แต่ถ้าบิลเป็นภาษาไทยอยู่แล้วให้สรุปได้เลย","category_th":"สรุปสั้นๆ บิลนี้คือค่าอะไร (เช่น ค่าไฟฟ้า, ค่าน้ำ,
  อินเทอร์เน็ต)","amount":"จำนวนเงินตัวเลขอย่างเดียว ถ้าไม่มีให้ว่าง","bill_date":"วันที่ในบิล YYYY-MM-DD ถ้าไม่มีให้ว่าง"}
  โดยไม่ต้องอธิบายเพิ่ม:\n\n${jpText}`;
              const gemRes = await fetch(
                `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent  ?key=${geminiKey}`,
                {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({
                    contents: [{ parts: [{ text: prompt }] }],
                    generationConfig: { responseMimeType: 'application/json' }
                  })
                }
              );
              const gemData = await gemRes.json();
              let gem = null;
              try {
                const txt = gemData.candidates && gemData.candidates[0] &&
  gemData.candidates[0].content.parts[0].text;
                gem = txt ? JSON.parse(txt) : null;
              } catch (e) { console.error('Gemini parse failed:', e); }
              if (gem) {
                body.ocrText = gem.thai_text || jpText;
                body.billSummary = gem.category_th || '';
                body.amount = gem.amount || '';
                body.billDate = gem.bill_date || '';
              } else {
                body.ocrText = jpText; // fallback เก็บภาษาญี่ปุ่นถ้า Gemini พลาด
              }
            } else {
              body.ocrText = jpText; // ไม่มี geminiKey → เก็บภาษาญี่ปุ่น
            }
            body.imageBase64 = base64;
          } else {
            console.error('LINE download not ok', msgRes.status);
          }
        } catch (e) {
          console.error('OCR/Line failed:', e);
        }
      }

      const nasRes = await fetch(
        "http://dmcyamanashi.myqnapcloud.com/yamafinance/public/api/line.php",
        {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-API-KEY": "Yama072+Finance@2026" },
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
