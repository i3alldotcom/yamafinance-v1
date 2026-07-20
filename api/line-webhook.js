module.exports = async function handler(req, res) {
  async function safeJson(response, label) {
    const text = await response.text();
    if (!text) {
      console.error(label, 'empty body, status', response.status);
      return null;
    }
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error(label, 'non-JSON body, status', response.status, 'preview', text.slice(0, 300));
      return null;
    }
  }

  try {
    const body = req.body;
    const event = body && body.events ? body.events[0] : null;
    const message = event ? event.message : null;

    if (message && message.type === 'image') {
      const token = process.env.LINE_CHANNEL_ACCESS_TOKEN;
      const visionKey = process.env.GOOGLE_VISION_API_KEY;
      const geminiKey = process.env.GEMINI_API_KEY;

      try {
        const msgRes = await fetch(
          'https://api-data.line.me/v2/bot/message/' + message.id + '/content',
          { headers: { Authorization: 'Bearer ' + token } }
        );

        if (msgRes.ok) {
          const buf = Buffer.from(await msgRes.arrayBuffer());
          const base64 = buf.toString('base64');

          body.imageBase64 = base64;

          let jpText = '';
          const visionRes = await fetch(
            'https://vision.googleapis.com/v1/images:annotate?key=' + visionKey,
            {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                requests: [{ image: { content: base64 }, features: [{ type: 'DOCUMENT_TEXT_DETECTION' }] }]
              })
            }
          );
          const visionData = await safeJson(visionRes, 'Vision');
          if (!visionRes.ok || !visionData) {
            console.error('Vision API error', visionRes.status);
          } else {
            const ann = visionData.responses &&
                        visionData.responses[0] &&
                        visionData.responses[0].fullTextAnnotation;
            jpText = ann ? ann.text : '';
          }

          body.ocrText = jpText;
อินเทอร์เน็ต)","amount":"จำนวนเงินตัวเลขอย่างเดียว ถ้าไม่มีให้ว่าง","bill_date":"วันที่ในบิล YYYY-MM-DD ถ้าไม่มีให้ว่าง"}\nโดยไม่ต้องอธิบายเพิ่ม:\n\n' + jpText;

            const gemRes = await fetch(
              'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' + geminiKey,
              {
                met
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  contents: [{ parts: [{ text: prompt }] }],
                  generationConfig: { responseMimeType: 'application/json' }
                })
              }
            );
            const gemData = await safeJson(gemRes, 'Gemini');
            let gem = null;
            if (gemRes.ok && gemData) {
              try {
                const txt = gemData.candidates &&
                            gemData.candidates[0] &&
                            gemData.candidates[0].content.parts[0].text;
                gem = txt ? JSON.parse(txt) : null;
              } catch (e) { console.error('Gemini parse failed', e); }
            }
            if (gem
    );
    if (!nasRes.ok) { res.status(500).send('NAS failed'); return; }
    res.status(200).send('OK');
  } catch (error) {
    console.error('Error', error);
    res.status(500).send('Error');
  }
};
