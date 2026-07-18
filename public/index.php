<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../public/dashboard.php");
    exit;
}

include('../config/db_connect.php');

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if ($row['is_active'] == 0) {
            $error = "บัญชีนี้ถูกปิดใช้งาน";
        } elseif (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id']   = $row['id'];
            $_SESSION['role']      = $row['role'];
            $_SESSION['full_name'] = $row['full_name'];
            header("Location: ../public/dashboard.php");
            exit;
        } else {
            $error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error = "ไม่พบชื่อผู้ใช้";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ — วัดพระธรรมกายยามานาชิ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Sarabun', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #f5f0ea;
            position: relative;
            overflow: hidden;
        }

        /* ลายเส้นซากุระ background */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 40% at 15% 50%, #e8d5c4 0%, transparent 60%),
                radial-gradient(ellipse 50% 60% at 85% 20%, #d4c5b8 0%, transparent 55%),
                radial-gradient(ellipse 40% 50% at 70% 80%, #c9b8ae 0%, transparent 50%);
            z-index: 0;
        }

        /* เส้นลายญี่ปุ่นบาง ๆ */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image:
                repeating-linear-gradient(
                    135deg,
                    transparent 0px,
                    transparent 48px,
                    rgba(180,150,120,.06) 48px,
                    rgba(180,150,120,.06) 49px
                );
            z-index: 0;
        }

        /* แผง layout */
        .login-wrapper {
            position: relative;
            z-index: 1;
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* แผงซ้าย — ธีม / โลโก้ */
        .left-panel {
            width: 45%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            background: rgba(92,60,40,.88);
            position: relative;
            overflow: hidden;
        }

        /* mon pattern โปร่งแสง */
        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                repeating-linear-gradient(0deg, rgba(255,255,255,.03) 0px, rgba(255,255,255,.03) 1px, transparent 1px, transparent 40px),
                repeating-linear-gradient(90deg, rgba(255,255,255,.03) 0px, rgba(255,255,255,.03) 1px, transparent 1px, transparent 40px);
        }

        /* วงกลมซากุระตกแต่ง */
        .deco-circle {
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,.08);
        }
        .deco-circle.c1 { width:320px; height:320px; top:-80px; right:-80px; }
        .deco-circle.c2 { width:200px; height:200px; bottom:40px; left:-60px; }
        .deco-circle.c3 { width:120px; height:120px; bottom:180px; right:40px; }

        /* เส้นคั่นแนวตั้งญี่ปุ่น */
        .left-panel::after {
            content: '';
            position: absolute;
            right: 0; top: 0; bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, transparent, rgba(205,160,100,.6), transparent);
        }

        .logo-area {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .logo-placeholder {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: rgba(255,255,255,.12);
            border: 2px solid rgba(255,255,255,.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            font-size: 48px;
            /* เปลี่ยน src ตรงนี้เป็นโลโก้วัดจริง */
        }

        /* ถ้ามีไฟล์โลโก้ → เปลี่ยน .logo-placeholder เป็น <img> แทน */

        .temple-th {
            font-size: 22px;
            font-weight: 600;
            color: #fff;
            letter-spacing: .04em;
            line-height: 1.5;
            margin-bottom: 6px;
        }

        .temple-jp {
            font-size: 13px;
            color: rgba(255,255,255,.55);
            letter-spacing: .08em;
            margin-bottom: 28px;
        }

        .system-name {
            font-size: 13px;
            color: rgba(255,220,160,.85);
            letter-spacing: .06em;
            border-top: 1px solid rgba(255,255,255,.15);
            padding-top: 20px;
            line-height: 1.7;
        }

        /* ดอกซากุระ SVG ลอย */
        .petals { position: absolute; inset: 0; overflow: hidden; pointer-events: none; z-index: 0; }
        .petal {
            position: absolute;
            opacity: .18;
            animation: fall linear infinite;
        }
        @keyframes fall {
            0%   { transform: translateY(-20px) rotate(0deg); opacity: .18; }
            100% { transform: translateY(110vh) rotate(360deg); opacity: 0; }
        }

        /* แผงขวา — ฟอร์ม */
        .right-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 32px;
        }

        .form-card {
            background: rgba(255,252,248,.92);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(180,150,110,.25);
            border-radius: 20px;
            padding: 48px 44px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 8px 40px rgba(80,50,20,.10);
        }

        .form-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .form-header h2 {
            font-size: 20px;
            font-weight: 600;
            color: #3d2b1a;
            margin-bottom: 6px;
        }

        .form-header p {
            font-size: 13px;
            color: #8a7060;
            letter-spacing: .03em;
        }

        /* เส้นตกแต่ง */
        .divider-ornament {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
        }
        .divider-ornament::before,
        .divider-ornament::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(150,110,70,.3), transparent);
        }
        .divider-ornament span {
            font-size: 16px;
            color: #a07850;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #5a3e2a;
            margin-bottom: 7px;
            letter-spacing: .02em;
        }

        .input-wrap {
            position: relative;
            margin-bottom: 20px;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #a08060;
            font-size: 16px;
        }

        input[type=text],
        input[type=password] {
            width: 100%;
            padding: 11px 14px 11px 42px;
            border: 1.5px solid #d8c8b0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Sarabun', sans-serif;
            background: rgba(255,252,248,.8);
            color: #3d2b1a;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }

        input[type=text]:focus,
        input[type=password]:focus {
            border-color: #c0905a;
            box-shadow: 0 0 0 3px rgba(192,144,90,.15);
        }

        input::placeholder { color: #b8a090; }

        .btn-login {
            width: 100%;
            padding: 13px;
            background: #7a4a28;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            font-family: 'Sarabun', sans-serif;
            cursor: pointer;
            letter-spacing: .04em;
            transition: background .2s, transform .1s;
            margin-top: 8px;
        }

        .btn-login:hover  { background: #6a3c20; }
        .btn-login:active { transform: scale(.98); }

        .error-box {
            background: #fdf0ed;
            border: 1px solid #e8c0b0;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 13px;
            color: #9a3a20;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-note {
            text-align: center;
            margin-top: 28px;
            font-size: 12px;
            color: #a09080;
        }

        /* Mobile */
        @media (max-width: 680px) {
            .left-panel { display: none; }
            .right-panel { padding: 24px 20px; }
            .form-card { padding: 36px 28px; }
        }
    </style>
</head>
<body>

<div class="login-wrapper">

    <!-- แผงซ้าย -->
    <div class="left-panel">
        <div class="petals" id="petals"></div>
        <div class="deco-circle c1"></div>
        <div class="deco-circle c2"></div>
        <div class="deco-circle c3"></div>

        <div class="logo-area">
            <!-- โลโก้วัด: เปลี่ยน emoji เป็น <img src="../assets/logo.png"> ได้เลย 
            <div class="logo-placeholder">🛕</div> -->     
            <!-- แทนที่ div.logo-placeholder ด้วย -->
<img src="../public/assets/logo.png"
     style="width:110px;height:110px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.25);">
            <div class="temple-th">
                วัดพระธรรมกาย<br>ยามานาชิ
            </div>
            <div class="temple-jp">
                やまなし法身寺 ・ ประเทศญี่ปุ่น
            </div>
            <div class="system-name">
                ระบบบริหารการเงิน<br>
                <span style="font-size:12px;opacity:.7;">Financial Management System</span>
            </div>
        </div>
    </div>

    <!-- แผงขวา -->
    <div class="right-panel">
        <div class="form-card">
            <div class="form-header">
                <h2>เข้าสู่ระบบ</h2>
                <p>ระบบบริหารการเงิน วัดพระธรรมกายยามานาชิ</p>
            </div>

            <div class="divider-ornament"><span>✦</span></div>

            <?php if ($error): ?>
            <div class="error-box">
                <span>⚠</span>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div>
                    <label for="username">ชื่อผู้ใช้</label>
                    <div class="input-wrap">
                        <span class="input-icon">👤</span>
                        <input type="text" id="username" name="username"
                               placeholder="กรอกชื่อผู้ใช้" required autofocus>
                    </div>
                </div>

                <div>
                    <label for="password">รหัสผ่าน</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="password" name="password"
                               placeholder="กรอกรหัสผ่าน" required>
                    </div>
                </div>

                <button type="submit" class="btn-login">เข้าสู่ระบบ</button>
            </form>

            <div class="footer-note">
                วัดพระธรรมกายยามานาชิ &nbsp;•&nbsp; ประเทศญี่ปุ่น
            </div>
        </div>
    </div>
</div>

<script>
// ดอกซากุระร่วง
(function() {
    const container = document.getElementById('petals');
    const shapes = ['❀','✿','❁','✾'];
    for (let i = 0; i < 12; i++) {
        const el = document.createElement('div');
        el.className = 'petal';
        el.textContent = shapes[i % shapes.length];
        el.style.cssText =
            'left:' + (Math.random() * 100) + '%;' +
            'font-size:' + (10 + Math.random() * 14) + 'px;' +
            'animation-duration:' + (8 + Math.random() * 10) + 's;' +
            'animation-delay:' + (Math.random() * 8) + 's;' +
            'top:-20px;';
        container.appendChild(el);
    }
})();
</script>

</body>
</html>