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
      const openaiKey = process.env.OPENAI_API_KEY;

      try {
        const lineUrl = 'https://api-data.line.me/v2/bot/message/' + message.id + '/content';
        const msgRes = await fetch(lineUrl, {
          headers: { Authorization: 'Bearer ' + token }
        });

        if (msgRes.ok) {
          const buf = Buffer.from(await msgRes.arrayBuffer());
          const base64 = buf.toString('base64');

          body.imageBase64 = base64;

          let jpText = '';
          const visionUrl = 'https://vision.googleapis.com/v1/images:annotate?key=' + visionKey;
          const visionBody = {
            requests: [{
              image: { content: base64 },
              features: [{ type: 'DOCUMENT_TEXT_DETECTION' }]
            }]
          };
          const visionRes = await fetch(visionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(visionBody)
          });
          const visionData = await safeJson(visionRes, 'Vision');
          if (!visionRes.ok || !visionData) {
            console.error('Vision API error', visionRes.status);
          } else {
            const r0 = visionData.responses && visionData.responses[0];
            const ann = r0 && r0.fullTextAnnotation;
            jpText = ann ? ann.text : '';
          }

          body.ocrText = jpText;

          if (jpText && openaiKey) {
            const promptLines = [
              'คุณคือผู้ช่วยบัญชี ต่อไปนี้คือข้อความจากบิลที่อ่านด้วย OCR (อาจเป็นภาษาญี่ปุ่นหรือภาษาอื่น)',
              'กรุณาตอบกลับเฉพาะ JSON รูปแบบนี้ โดยไม่ต้องอธิบายเพิ่ม:',
              '{',
              '  "thai_text": "แปลเป็นภาษาไทยทั้งหมด ถ้าเป็นภาษาไทยอยู่แล้วให้สรุปได้เลย",',
              '  "category_th": "สรุปสั้นๆ ว่าบิลนี้คือค่าอะไร เช่น ค่าไฟฟ้า ค่าน้ำ อินเทอร์เน็ต",',
              '  "amount": "จำนวนเงินตัวเลขอย่างเดียว ถ้าไม่มีให้ว่าง",',
              '  "bill_date": "วันที่ในบิลรูปแบบ YYYY-MM-DD ถ้าไม่มีให้ว่าง"',
              '}',
              '',
              'ข้อความจากบิล:',
              jpText
            ];
            const userPrompt = promptLines.join('\n');
            console.log('OpenAI prompt length', userPrompt.length,
              'preview', userPrompt.slice(0, 300));

            const openaiUrl = 'https://api.openai.com/v1/chat/completions';
            const openaiBody = {
              model: 'gpt-4o-mini',
              messages: [
                { role: 'system', content: 'คุณคือผู้ช่วยบัญชีที่แปลบิลและสกัดข้อมูลเป็น JSON เท่านั้น' },
                { role: 'user', content: userPrompt }
              ],
              response_format: { type: 'json_object' },
              temperature: 0.2
            };
            // เรียก OpenAI พร้อม retry เมื่อเจอ 429 (rate limit)
            let openaiRes = null;
            let openaiData = null;
            const maxAttempts = 3;
            for (let attempt = 1; attempt <= maxAttempts; attempt++) {
              openaiRes = await fetch(openaiUrl, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json',
                  'Authorization': 'Bearer ' + openaiKey
                },
                body: JSON.stringify(openaiBody)
              });
              openaiData = await safeJson(openaiRes, 'OpenAI');
              if (openaiRes.ok && openaiData) break;
              if (openaiRes.status === 429 && attempt < maxAttempts) {
                const waitMs = 800 * attempt; // 800ms, 1600ms
                const h = openaiRes.headers;
                console.error('OpenAI 429, retry in', waitMs, 'ms (attempt', attempt, ')',
                  'ratelimit', h.get('x-ratelimit-remaining-requests'),
                  'limit', h.get('x-ratelimit-limit-requests'),
                  'reset', h.get('x-ratelimit-reset-requests'),
                  'retryAfter', h.get('retry-after'));
                await new Promise(function (r) { setTimeout(r, waitMs); });
              } else {
                break;
              }
            }
            let gem = null;
            if (!openaiRes.ok || !openaiData) {
              const h = openaiRes.headers;
              console.error('OpenAI API not ok', openaiRes.status,
                'ratelimit', h.get('x-ratelimit-remaining-requests'),
                'limit', h.get('x-ratelimit-limit-requests'),
                'reset', h.get('x-ratelimit-reset-requests'),
                'retryAfter', h.get('retry-after'));
            } else {
              const choice = openaiData.choices && openaiData.choices[0];
              const finishReason = choice && choice.finishReason;
              let txt = '';
              if (choice && choice.message && choice.message.content) {
                txt = choice.message.content || '';
              }
              console.log('OpenAI finishReason', finishReason, 'rawText', txt.slice(0, 400));

              // แกะ markdown fence ถ้ามี (```json ... ```) ก่อน parse
              let clean = txt.trim();
              if (clean.indexOf('```') !== -1) {
                clean = clean.replace(/```json/gi, '').replace(/```/g, '').trim();
              }
              try {
                gem = clean ? JSON.parse(clean) : null;
              } catch (e) {
                console.error('OpenAI parse failed', e.message, 'cleanText', clean.slice(0, 400));
              }
            }
            if (gem) {
              body.ocrText = gem.thai_text || jpText;
              body.billSummary = gem.category_th || '';
              body.amount = gem.amount || '';
              body.billDate = gem.bill_date || '';
            }
          }
        } else {
          console.error('LINE download not ok', msgRes.status);
        }
      } catch (e) {
        console.error('OCR/Line failed', e);
      }
    }

    const nasUrl = 'http://dmcyamanashi.myqnapcloud.com/yamafinance/public/api/line.php';
    const nasRes = await fetch(nasUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-KEY': 'Yama072+Finance@2026'
      },
      body: JSON.stringify(body)
    });
    if (!nasRes.ok) {
      res.status(500).send('NAS failed');
      return;
    }
    res.status(200).send('OK');
  } catch (error) {
    console.error('Error', error);
    res.status(500).send('Error');
  }
};
