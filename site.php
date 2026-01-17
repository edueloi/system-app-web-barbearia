<?php
    // --- Config ---
    $meu_whatsapp = "5515992897425";
    $mensagem_whatsapp = "Olá! Tenho interesse no Develoi Agenda e gostaria de solicitar uma demonstração.";
    $mensagem_compra = "Olá! Quero assinar o Develoi Agenda no valor de R$ 69,90 por mês. Como faço para liberar o acesso?";
    $nome_robo = "Assistente Develoi";
    $url_logo = "https://salao.develoi.com/img/logo-azul.png"; // ajuste se quiser
    $url_site = "https://salao.develoi.com";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
    <title>Develoi Agenda • Sistema de Gestão para Salões, Barbearias e Estética</title>

    <!-- SEO -->
    <meta name="description" content="Sistema completo de gestão para salões, barbearias e estética: agenda online, estoque fracionado, financeiro, comissões, confirmação via WhatsApp. Plano mensal de R$ 69,90.">
    <meta name="keywords" content="sistema salão, sistema barbearia, agenda online, agendamento whatsapp, controle estoque fracionado, gestão financeira salão, comissões, recibo, sistema estética">
    <meta name="author" content="Develoi">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#4f46e5">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Develoi Agenda - Gestão Premium para Salões e Barbearias">
    <meta property="og:description" content="Agenda, estoque, financeiro e confirmação por WhatsApp. Plano mensal de R$ 69,90.">
    <meta property="og:image" content="<?php echo htmlspecialchars($url_logo); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($url_site); ?>">
    <meta property="og:site_name" content="Develoi Agenda">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="Develoi Agenda - Sistema para Salões e Barbearias">
    <meta property="twitter:description" content="Controle total do seu negócio em uma única plataforma.">
    <meta property="twitter:image" content="<?php echo htmlspecialchars($url_logo); ?>">

    <link rel="canonical" href="<?php echo htmlspecialchars($url_site); ?>">

    <!-- Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SoftwareApplication",
      "name": "Develoi Agenda",
      "applicationCategory": "BusinessApplication",
      "description": "Sistema completo de gestão para salões, barbearias e estética com agenda, estoque fracionado, financeiro, comissões e confirmação via WhatsApp.",
      "operatingSystem": "Web",
      "offers": {
        "@type": "Offer",
        "price": "69.90",
        "priceCurrency": "BRL",
        "description": "Plano mensal de R$ 69,90"
      },
      "url": "<?php echo htmlspecialchars($url_site); ?>",
      "image": "<?php echo htmlspecialchars($url_logo); ?>",
      "developer": {
        "@type": "Organization",
        "name": "Develoi"
      }
    }
    </script>

    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars($url_logo); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800&family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --primary:#4f46e5;
            --primary-dark:#4338ca;
            --secondary:#ec4899;
            --accent:#8b5cf6;
            --dark:#0f172a;
            --light:#f8fafc;
            --success:#10b981;
            --warn:#f59e0b;
            --danger:#ef4444;

            --glass-bg: rgba(255,255,255,.75);
            --glass-border: rgba(255,255,255,.55);
            --shadow-soft: 0 18px 40px -10px rgba(0,0,0,.08);
            --shadow-strong: 0 25px 60px -15px rgba(15, 23, 42, 0.15);
        }

        *{ box-sizing:border-box; -webkit-tap-highlight-color: transparent; }
        html, body { height: 100%; }
        body {
            font-family: 'Nunito', 'Plus Jakarta Sans', sans-serif;
            background: #f8fafc;
            color: var(--dark);
            overflow-x:hidden;
        }
        h1,h2,h3,h4,h5,.brand-text{ font-family:'Outfit', sans-serif; }
        a{ text-decoration:none; }

        /* Scrollbar */
        ::-webkit-scrollbar{ width:8px; }
        ::-webkit-scrollbar-track{ background:#f1f5f9; }
        ::-webkit-scrollbar-thumb{ background:#cbd5e1; border-radius:10px; }
        ::-webkit-scrollbar-thumb:hover{ background:#94a3b8; }

        /* Aurora BG */
        .aurora-bg{
            position:fixed; inset:0; z-index:-1; overflow:hidden;
            background: linear-gradient(180deg, #eef2ff 0%, #ffffff 100%);
        }
        .aurora-blob{
            position:absolute; border-radius:50%;
            filter: blur(90px); opacity:.55;
            animation: floatBlob 16s infinite alternate;
        }
        .blob-1{ top:-12%; left:-12%; width:60vw; height:60vw; background:#c7d2fe; animation-duration:22s; }
        .blob-2{ bottom:-12%; right:-12%; width:50vw; height:50vw; background:#fbcfe8; animation-duration:18s; }
        .blob-3{ top:35%; right:10%; width:35vw; height:35vw; background:#ddd6fe; animation-duration:26s; opacity:.35; }
        @keyframes floatBlob { 0%{ transform:translate(0,0) } 100%{ transform:translate(50px,-35px)} }

        /* Navbar */
        .navbar{
            padding: 1rem 0;
            background: transparent;
            transition: all .3s ease;
            z-index: 100;
        }
        .navbar.scrolled{
            background: rgba(255,255,255,.85);
            backdrop-filter: blur(12px);
            border-bottom:1px solid rgba(0,0,0,.05);
            padding: .75rem 0;
        }

        .brand-badge{
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 14px;
            display:flex; align-items:center; justify-content:center;
            color:#fff; font-size:2.1rem; font-weight:800;
            box-shadow: 0 8px 32px rgba(79,70,229,.12);
        }
        .brand-word{
            font-size: 1.7rem; font-weight:700; color: var(--dark);
            text-transform:uppercase; letter-spacing: .11em;
            margin-left: 2px;
        }
        .hover-primary{ transition:.2s ease; }
        .hover-primary:hover{ color: var(--primary) !important; }

        .menu-toggle{
            width: 44px; height: 44px;
            background: #fff;
            border-radius: 14px;
            display:flex; align-items:center; justify-content:center;
            border: 1px solid #e2e8f0;
            cursor:pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,.06);
        }
        .mobile-menu{
            position: fixed; inset:0;
            background: rgba(255,255,255,.98);
            backdrop-filter: blur(20px);
            z-index: 2000;
            opacity:0; visibility:hidden;
            transition: all .35s ease;
            display:flex; align-items:center; justify-content:center;
            flex-direction:column;
            padding: 24px;
        }
        .mobile-menu.active{ opacity:1; visibility:visible; }
        body.menu-open{ overflow:hidden; }
        body.menu-open .sticky-mobile,
        body.menu-open .chat-trigger,
        body.menu-open .chat-window{ opacity:0; pointer-events:none; }
        .mobile-link{
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--dark);
            margin: 12px 0;
        }
        .mobile-sub{
            color:#64748b; font-size:.95rem;
            margin-top:-6px;
        }

        /* Hero */
        .hero{
            padding-top: 140px;
            padding-bottom: 80px;
            position: relative;
        }
        .badge-soft{
            background: rgba(16,185,129,.12);
            color: var(--success);
            border: 1px solid rgba(16,185,129,.22);
            padding: .45rem .75rem;
            border-radius: 999px;
            display:inline-flex; gap:.5rem; align-items:center;
            box-shadow: 0 10px 30px -15px rgba(0,0,0,.15);
        }
        .hero-title{
            font-size: 3.3rem;
            font-weight: 900;
            letter-spacing: -.02em;
            line-height: 1.08;
        }
        @media (max-width: 768px){
            body{ font-size: 0.9rem; }
            h1{ font-size: 2rem; }
            h2{ font-size: 1.6rem; }
            h3{ font-size: 1.35rem; }
            .display-4{ font-size: 2rem; }
            .display-5{ font-size: 1.8rem; }
            .display-6{ font-size: 1.6rem; }
            .hero-title{ font-size: 1.85rem; }
            .brand-badge{ width: 34px; height: 34px; font-size: 1.5rem; border-radius: 12px; }
            .brand-word{ font-size: 1.2rem; letter-spacing: .07em; }
            .mobile-link{ font-size: 1.05rem; }
            .section{ padding: 56px 0; }
            .feature-box h4{ font-size: 1.05rem; }
            .feature-box p{ font-size: 0.85rem; }
            .accordion-button{ font-size: 0.95rem; }
            .accordion-body{ font-size: 0.9rem; }
            .btn, .btn-main, .btn-soft, .btn-light, .btn-outline-light{
                padding: 9px 18px;
                font-size: 0.85rem;
            }
        }
        .text-gradient{
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
        }
        .typing-cursor{
            display:inline-block; width:4px; height: 1em;
            background: var(--primary);
            margin-left: 4px;
            animation: blink .8s infinite;
            vertical-align: middle;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0} }

        /* Buttons */
        .btn-main{
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color:#fff;
            border: none;
            padding: 14px 28px;
            border-radius: 999px;
            font-weight: 700;
            box-shadow: 0 18px 40px -10px rgba(79,70,229,.45);
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .btn-main:hover{
            transform: translateY(-2px);
            box-shadow: 0 24px 50px -12px rgba(236,72,153,.45);
            color:#fff;
        }
        .btn-soft{
            background:#fff;
            color: var(--dark);
            border: 1px solid #e2e8f0;
            padding: 14px 28px;
            border-radius: 999px;
            font-weight: 700;
            transition: all .25s ease;
        }
        .btn-soft:hover{
            border-color: var(--primary);
            color: var(--primary);
            background: #eef2ff;
        }

        /* Trust Row */
        .trust-row{
            display:flex; gap: 18px; flex-wrap: wrap;
            align-items:center; opacity:.9;
        }
        .trust-pill{
            background: rgba(255,255,255,.65);
            border: 1px solid rgba(255,255,255,.8);
            backdrop-filter: blur(10px);
            padding: 10px 14px;
            border-radius: 999px;
            display:flex; gap:10px; align-items:center;
            box-shadow: var(--shadow-soft);
        }
        .trust-pill i{ color: var(--primary); }

        /* Mockup */
        .mockup-wrap{
            position: relative;
            perspective: 1000px;
        }
        .phone{
            width: 320px;
            height: 640px;
            max-width: 100%;
            background:#fff;
            border-radius: 52px;
            box-shadow:
                0 0 0 10px #fff,
                0 0 0 11px #e2e8f0,
                0 40px 100px -25px rgba(0,0,0,.22);
            overflow:hidden;
            margin: 0 auto;
            transform: rotateY(-6deg) rotateX(4deg);
            transition: transform .45s ease;
            animation: floatPhone 6s ease-in-out infinite;
        }
        .mockup-wrap:hover .phone{ transform: rotateY(0) rotateX(0); }
        @keyframes floatPhone { 0%,100%{ transform: translateY(0) rotateY(-6deg) rotateX(4deg) } 50%{ transform: translateY(-12px) rotateY(-6deg) rotateX(4deg) } }

        .phone-screen{
            background:#f8fafc;
            height:100%;
            padding: 22px 18px;
            display:flex;
            flex-direction:column;
            gap: 10px;
        }
        .mini-card{
            background:#fff;
            border-radius: 18px;
            padding: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,.04);
            display:flex;
            gap: 12px;
            align-items:center;
            opacity:0;
            transform: translateY(18px);
            animation: slideUp .6s forwards;
        }
        .mini-card:nth-child(2){ animation-delay: .10s; }
        .mini-card:nth-child(3){ animation-delay: .20s; }
        .mini-card:nth-child(4){ animation-delay: .30s; }

        @keyframes slideUp{
            to{ opacity:1; transform: translateY(0); }
        }

        .dark-revenue{
            background: #0f172a;
            color:#fff;
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 18px 46px rgba(15,23,42,.25);
            margin-top: 6px;
        }
        .progress-soft{
            height: 7px;
            background: rgba(255,255,255,.14);
            border-radius: 10px;
            overflow:hidden;
        }
        .progress-bar-soft{
            width: 72%;
            height: 100%;
            background: #22c55e;
            border-radius: 10px;
        }

        .zap-pop{
            position:absolute;
            right:-30px;
            top: 25%;
            width: 270px;
            background:#fff;
            border-radius: 18px;
            padding: 14px 16px;
            box-shadow: 0 15px 50px rgba(0,0,0,.12);
            display:flex; gap: 12px; align-items:center;
            border-left: 4px solid #25D366;
            opacity:0;
            transform: scale(.92) translateX(24px);
            animation: popIn .6s cubic-bezier(.34,1.56,.64,1) 1.2s forwards;
        }
        @keyframes popIn{ to{ opacity:1; transform: scale(1) translateX(0); } }

        @media(max-width: 991px){
            .zap-pop{
                position:relative;
                right:auto;
                top:auto;
                margin: 0 auto 16px;
                transform:none;
                opacity:1;
                animation:none;
                width: min(380px, 100%);
            }
            .phone{ margin-top: 18px; }
        }

        /* Sections */
        .section{ padding: 84px 0; }
        .section-dark{
            background: #0f172a;
            color:#fff;
            position:relative;
            overflow:hidden;
        }
        .section-dark::before{
            content:'';
            position:absolute; inset:-40%;
            background: radial-gradient(circle at 40% 40%, rgba(79,70,229,.35), transparent 55%),
                        radial-gradient(circle at 70% 65%, rgba(236,72,153,.28), transparent 55%);
            opacity:.9;
        }
        .section-dark > .container{ position:relative; z-index:2; }

        .card-glass{
            background: rgba(255,255,255,.08);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 24px;
            padding: 26px;
            height: 100%;
            transition: transform .25s ease, border-color .25s ease, background .25s ease;
        }
        .card-glass:hover{
            transform: translateY(-6px);
            background: rgba(255,255,255,.10);
            border-color: rgba(255,255,255,.22);
        }
        .icon-bubble{
            width: 56px; height: 56px;
            border-radius: 18px;
            display:flex; align-items:center; justify-content:center;
            background: rgba(255,255,255,.10);
            border: 1px solid rgba(255,255,255,.16);
            margin-bottom: 14px;
        }

        /* Feature grid */
        .feature{
            background: rgba(255,255,255,.60);
            border: 1px solid rgba(255,255,255,.85);
            border-radius: 26px;
            padding: 30px;
            height: 100%;
            backdrop-filter: blur(14px);
            box-shadow: var(--shadow-soft);
            transition: transform .25s ease, background .25s ease;
        }
        .feature:hover{
            transform: translateY(-6px);
            background:#fff;
        }
        .feature i{
            font-size: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
        }

        /* Pricing */
        .price-card{
            background: #0f172a;
            color:#fff;
            border-radius: 30px;
            padding: 34px;
            box-shadow: var(--shadow-strong);
            border: 1px solid rgba(255,255,255,.08);
            position: relative;
            overflow:hidden;
        }
        .price-card::before{
            content:'';
            position:absolute; inset:-30%;
            background: radial-gradient(circle at 30% 20%, rgba(79,70,229,.35), transparent 45%),
                        radial-gradient(circle at 70% 75%, rgba(236,72,153,.25), transparent 45%);
            opacity:.9;
        }
        .price-card > * { position:relative; z-index:2; }
        .price-tag{
            display:inline-flex; gap:8px; align-items:center;
            background: rgba(16,185,129,.14);
            color: #34d399;
            border: 1px solid rgba(16,185,129,.22);
            padding: 8px 12px;
            border-radius: 999px;
            font-weight: 800;
            font-size: .9rem;
        }
        .check li{
            margin: 10px 0;
            color: rgba(255,255,255,.78);
        }
        .check i{ color:#34d399; }

        /* Comparison */
        .compare{
            background:#fff;
            border-radius: 28px;
            border:1px solid #e2e8f0;
            box-shadow: var(--shadow-soft);
            overflow:hidden;
        }
        .compare .head{
            background: #f8fafc;
            border-bottom:1px solid #e2e8f0;
        }
        .compare .rowx{
            display:flex; justify-content:space-between; gap: 16px;
            padding: 14px 18px;
            border-bottom:1px solid #eef2f7;
        }
        .compare .rowx:last-child{ border-bottom:none; }
        .pill-ok{
            background: rgba(16,185,129,.12);
            color: #047857;
            border:1px solid rgba(16,185,129,.25);
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 800;
            font-size: .82rem;
            white-space: nowrap;
        }
        .pill-no{
            background: rgba(239,68,68,.10);
            color: #b91c1c;
            border:1px solid rgba(239,68,68,.22);
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 800;
            font-size: .82rem;
            white-space: nowrap;
        }

        /* Testimonials */
        .t-card{
            background: rgba(255,255,255,.62);
            border: 1px solid rgba(255,255,255,.9);
            border-radius: 26px;
            padding: 26px;
            height: 100%;
            box-shadow: var(--shadow-soft);
        }
        .stars{ color: #f59e0b; }

        /* FAQ */
        .accordion-item{ border:none; background: transparent; margin-bottom: 12px; }
        .accordion-button{
            border-radius: 18px !important;
            background: #fff !important;
            box-shadow: 0 6px 18px rgba(0,0,0,.04);
            padding: 1.1rem;
            font-weight: 800;
        }
        .accordion-button:not(.collapsed){
            color: var(--primary);
            box-shadow: 0 14px 30px rgba(79,70,229,.14);
        }
        .accordion-button:focus{ box-shadow:none; }
        .accordion-body{
            background:#fff;
            border-radius: 0 0 18px 18px;
            margin-top:-10px;
            padding: 1.35rem 1.25rem 1.25rem;
            color:#64748b;
        }

        /* CTA final */
        .cta{
            background: #0f172a;
            border-radius: 40px;
            padding: 76px 20px;
            color:#fff;
            position: relative;
            overflow:hidden;
            margin: 0 18px;
        }
        .cta-blob{
            position:absolute;
            width: 420px; height: 420px;
            filter: blur(90px);
            opacity:.35;
            border-radius:50%;
        }

        /* Sticky mobile bar */
        .sticky-mobile{
            position: fixed;
            left: 0; right: 0; bottom: 0;
            padding: 12px;
            z-index: 999;
            display:none;
        }
        @media(max-width: 991px){
            .sticky-mobile{ display:block; }
        }
        .sticky-inner{
            background: rgba(15,23,42,.95);
            color:#fff;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.12);
            padding: 10px 10px 10px 18px;
            display:flex; align-items:center; justify-content:space-between;
            backdrop-filter: blur(12px);
            box-shadow: 0 18px 45px rgba(0,0,0,.25);
        }

        /* Whats chat bubble + window */
        .chat-trigger{
            position: fixed;
            right: 20px;
            bottom: 20px;
            width: 62px; height: 62px;
            border-radius: 50%;
            background: linear-gradient(135deg,#25D366,#128C7E);
            box-shadow: 0 8px 22px rgba(0,0,0,.2);
            display:flex; align-items:center; justify-content:center;
            cursor:pointer;
            z-index: 1000;
            animation: pulseGreen 2s infinite;
        }
        @media(max-width: 991px){
            .chat-trigger{ bottom: 88px; }
        }
        .chat-trigger i{ color:#fff; font-size: 30px; }
        @keyframes pulseGreen{
            0%{ box-shadow:0 0 0 0 rgba(37,211,102,.65), 0 8px 22px rgba(0,0,0,.2); }
            70%{ box-shadow:0 0 0 16px rgba(37,211,102,0), 0 8px 22px rgba(0,0,0,.2); }
            100%{ box-shadow:0 0 0 0 rgba(37,211,102,0), 0 8px 22px rgba(0,0,0,.2); }
        }
        .chat-window{
            position: fixed;
            right: 20px;
            bottom: 92px;
            width: 360px;
            max-width: calc(100vw - 40px);
            height: 520px;
            background:#fff;
            border-radius: 22px;
            box-shadow: 0 20px 60px rgba(0,0,0,.18);
            overflow:hidden;
            z-index: 1000;
            opacity:0;
            pointer-events:none;
            transform: translateY(16px);
            transition: all .25s ease;
            display:flex;
            flex-direction:column;
        }
        .chat-window.active{
            opacity:1;
            pointer-events:all;
            transform: translateY(0);
        }
        .chat-header{
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 14px;
            color:#fff;
            display:flex;
            align-items:center;
            gap: 10px;
        }
        .chat-avatar{
            width: 42px; height: 42px;
            border-radius: 50%;
            background: rgba(255,255,255,.18);
            display:flex; align-items:center; justify-content:center;
        }
        .chat-close{ margin-left:auto; cursor:pointer; opacity:.9; }
        .chat-body{
            flex:1;
            padding: 14px;
            background:#f8fafc;
            overflow-y:auto;
            display:flex;
            flex-direction:column;
            gap: 10px;
        }
        .msg{
            max-width: 86%;
            padding: 10px 14px;
            border-radius: 16px;
            font-size: .9rem;
            line-height: 1.35;
            animation: msgIn .18s ease;
        }
        @keyframes msgIn { from{ opacity:0; transform: translateY(6px);} to{ opacity:1; transform: translateY(0);} }
        .msg-bot{
            background:#e2e8f0;
            color:#0f172a;
            border-bottom-left-radius: 4px;
            align-self:flex-start;
        }
        .msg-user{
            background: #4f46e5;
            color:#fff;
            border-bottom-right-radius: 4px;
            align-self:flex-end;
        }
        .chat-options{
            display:flex;
            flex-direction:column;
            gap: 8px;
            margin-top: 6px;
        }
        .btn-option{
            background:#fff;
            border:1px solid #4f46e5;
            color:#4f46e5;
            padding: 9px 12px;
            border-radius: 999px;
            font-size: .86rem;
            cursor:pointer;
            text-align:left;
            display:flex;
            justify-content:space-between;
            align-items:center;
            transition: all .18s ease;
        }
        .btn-option:hover{
            background:#4f46e5;
            color:#fff;
        }
        .typing-indicator{
            font-size: .75rem;
            color:#94a3b8;
            padding: 8px 14px;
            display:none;
        }

        /* Small helpers */
        .muted{ color:#64748b; }
        .shadow-soft{ box-shadow: var(--shadow-soft); }
        .rounded-2xl{ border-radius: 24px; }
        .ls-1{ letter-spacing:.12em; }
    </style>
</head>

<body>
    <div class="aurora-bg">
        <div class="aurora-blob blob-1"></div>
        <div class="aurora-blob blob-2"></div>
        <div class="aurora-blob blob-3"></div>
    </div>

    <!-- Mobile menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="text-center mb-3">
            <div class="d-inline-flex align-items-center gap-2">
                <div class="brand-badge">D</div>
                <div class="brand-word">EVELOI</div>
            </div>
            <div class="mobile-sub mt-2">Gestão completa. Simples de usar. Feita pra dar lucro.</div>
        </div>
        <a class="mobile-link" href="#inicio" onclick="toggleMenu()">Início</a>
        <a class="mobile-link" href="#dor" onclick="toggleMenu()">O problema</a>
        <a class="mobile-link" href="#recursos" onclick="toggleMenu()">Funcionalidades</a>
        <a class="mobile-link" href="#prova" onclick="toggleMenu()">Resultados</a>
        <a class="mobile-link" href="#preco" onclick="toggleMenu()">Preço</a>
        <a class="mobile-link" href="#faq" onclick="toggleMenu()">Dúvidas</a>
        <div class="d-flex flex-column gap-2 mt-4 w-100" style="max-width: 420px;">
            <a class="btn btn-main w-100" href="#preco" onclick="toggleMenu()">
                Quero assinar agora <i class="fa-solid fa-arrow-right ms-2"></i>
            </a>
            <a class="btn btn-soft w-100" target="_blank" rel="noopener"
               href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>">
                <i class="fa-brands fa-whatsapp me-2 text-success"></i> Falar no WhatsApp
            </a>
            <button class="btn btn-link text-muted" onclick="toggleMenu()">Fechar</button>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar fixed-top">
        <div class="container d-flex align-items-center justify-content-between">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#inicio">
                <div class="brand-badge">D</div>
                <span class="brand-word">EVELOI</span>
            </a>

            <div class="d-none d-lg-flex align-items-center gap-4">
                <a href="#dor" class="fw-semibold text-secondary hover-primary">O problema</a>
                <a href="#recursos" class="fw-semibold text-secondary hover-primary">Recursos</a>
                <a href="#prova" class="fw-semibold text-secondary hover-primary">Resultados</a>
                <a href="#preco" class="fw-semibold text-secondary hover-primary">Preço</a>
                <a href="#faq" class="fw-semibold text-secondary hover-primary">FAQ</a>
                <a href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>"
                   target="_blank" rel="noopener"
                   class="btn btn-dark rounded-pill px-4">
                    Demo no WhatsApp
                </a>
            </div>

            <div class="menu-toggle d-lg-none" onclick="toggleMenu()">
                <i class="fa-solid fa-bars text-dark fs-5"></i>
            </div>
        </div>
    </nav>

    <!-- HERO -->
    <section id="inicio" class="hero">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6 order-2 order-lg-1">
                    <div data-aos="fade-up">
                        <div class="badge-soft mb-4">
                            <i class="fa-solid fa-bolt"></i>
                            <span class="fw-bold">Painel 2.0 • Mais rápido e mais bonito</span>
                        </div>

                        <h1 class="hero-title mb-3">
                            Pare de <span class="text-gradient">perder dinheiro</span> no detalhe.<br>
                            Domine <span id="typingText" class="text-gradient"></span><span class="typing-cursor"></span>
                        </h1>

                        <p class="fs-5 text-secondary mb-4" style="line-height:1.6;">
                            O Develoi Agenda organiza sua agenda, calcula o lucro real por serviço, controla estoque fracionado
                            e ainda te ajuda com confirmação via WhatsApp — sem travar seu atendimento.
                        </p>

                        <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
                            <a href="#preco" class="btn btn-main d-flex align-items-center justify-content-center gap-2">
                                Quero assinar por R$ 69,90/mês <i class="fa-solid fa-arrow-right"></i>
                            </a>
                            <a target="_blank" rel="noopener"
                               href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>"
                               class="btn btn-soft d-flex align-items-center justify-content-center gap-2">
                                <i class="fa-brands fa-whatsapp fs-5 text-success"></i> Quero uma demonstração
                            </a>
                        </div>

                        <div class="trust-row mt-3">
                            <div class="trust-pill">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span class="fw-semibold">Sem fidelidade</span>
                            </div>
                            <div class="trust-pill">
                                <i class="fa-solid fa-headset"></i>
                                <span class="fw-semibold">Suporte humano</span>
                            </div>
                            <div class="trust-pill">
                                <i class="fa-solid fa-mobile-screen"></i>
                                <span class="fw-semibold">Celular & PC</span>
                            </div>
                        </div>

                        <div class="mt-4 text-secondary small">
                            <i class="fa-solid fa-circle-check text-success me-2"></i>
                            Comece em minutos • Treinamento simples • Sem aplicativo
                        </div>
                    </div>
                </div>

                <div class="col-lg-6 order-1 order-lg-2" data-aos="fade-left" data-aos-delay="150">
                    <div class="mockup-wrap">
                        <div class="zap-pop">
                            <div style="width:38px;height:38px;border-radius:50%;background:#25D366;color:#fff;display:flex;align-items:center;justify-content:center;">
                                <i class="fa-brands fa-whatsapp"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block" style="font-size:.72rem;">Agora</small>
                                <strong class="text-dark" style="font-size:.86rem;line-height:1.2;">
                                    Cliente confirmou o horário.<br>Agenda organizada ✅
                                </strong>
                            </div>
                        </div>

                        <div class="phone">
                            <div class="phone-screen">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <div>
                                        <small class="text-muted d-block" style="font-size:.75rem;">Painel do dia</small>
                                        <h6 class="fw-bold m-0">Studio Elite</h6>
                                    </div>
                                    <img src="https://ui-avatars.com/api/?name=User&background=random" class="rounded-circle" width="40" alt="User">
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="fw-bold text-dark">Agenda de hoje</span>
                                    <span class="badge bg-dark rounded-pill">26 Nov</span>
                                </div>

                                <div class="mini-card">
                                    <div class="fw-bold fs-5 text-dark" style="min-width:58px;">09:00</div>
                                    <div style="width:4px;height:40px;background:#ec4899;border-radius:4px;"></div>
                                    <div>
                                        <div class="fw-bold text-dark small">Corte & Barba</div>
                                        <div class="text-muted" style="font-size:.75rem;">Cliente: Marcos P.</div>
                                    </div>
                                    <span class="badge bg-success-subtle text-success ms-auto">Confirmado</span>
                                </div>

                                <div class="mini-card">
                                    <div class="fw-bold fs-5 text-dark" style="min-width:58px;">10:30</div>
                                    <div style="width:4px;height:40px;background:#4f46e5;border-radius:4px;"></div>
                                    <div>
                                        <div class="fw-bold text-dark small">Hidratação</div>
                                        <div class="text-muted" style="font-size:.75rem;">Cliente: Ana Júlia</div>
                                    </div>
                                    <span class="badge bg-warning-subtle text-warning ms-auto">Pendente</span>
                                </div>

                                <div class="mini-card">
                                    <div class="fw-bold fs-5 text-dark" style="min-width:58px;">12:00</div>
                                    <div style="width:4px;height:40px;background:#10b981;border-radius:4px;"></div>
                                    <div>
                                        <div class="fw-bold text-dark small">Manicure</div>
                                        <div class="text-muted" style="font-size:.75rem;">Cliente: Bruna</div>
                                    </div>
                                    <span class="badge bg-success-subtle text-success ms-auto">Confirmado</span>
                                </div>

                                <div class="dark-revenue">
                                    <small class="text-white-50">Faturamento previsto de hoje</small>
                                    <h3 class="fw-bold m-0 mt-1">R$ 480,00</h3>
                                    <div class="d-flex justify-content-between mt-2 text-white-50" style="font-size:.75rem;">
                                        <span>72% da meta</span>
                                        <span class="text-success fw-bold">+18% vs semana passada</span>
                                    </div>
                                    <div class="progress-soft mt-2">
                                        <div class="progress-bar-soft"></div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="text-center mt-3 text-secondary small">
                            <i class="fa-solid fa-circle-play me-2 text-primary"></i>
                            Mostre isso pro cliente: “agenda online + confirmação no WhatsApp”
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- DOR / PROVOCATIVO -->
    <section id="dor" class="section section-dark">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill"
                     style="background: rgba(239,68,68,.12); border:1px solid rgba(239,68,68,.18); color:#fecaca;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span class="fw-bold">Pare de perder dinheiro</span>
                </div>
                <h2 class="display-6 fw-bold mt-3">Agenda cheia não significa conta cheia.</h2>
                <p class="text-white-50 fs-5 mx-auto" style="max-width: 760px;">
                    Se você não controla estes pontos, você trabalha muito… e o lucro some.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="card-glass">
                        <div class="icon-bubble text-danger"><i class="fa-solid fa-user-xmark fs-4"></i></div>
                        <h4 class="fw-bold mb-2">A cadeira vazia</h4>
                        <p class="text-white-50 mb-0">
                            “Bolo” e falta sem aviso derrubam o faturamento. O Develoi facilita confirmação e reduz furos na agenda.
                        </p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="card-glass">
                        <div class="icon-bubble text-warning"><i class="fa-solid fa-fill-drip fs-4"></i></div>
                        <h4 class="fw-bold mb-2">O “chutômetro”</h4>
                        <p class="text-white-50 mb-0">
                            Cobrar sem saber custo real do produto é prejuízo silencioso. Estoque fracionado mostra o custo exato do serviço.
                        </p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="card-glass">
                        <div class="icon-bubble text-primary"><i class="fa-brands fa-whatsapp fs-4"></i></div>
                        <h4 class="fw-bold mb-2">Escravo do WhatsApp</h4>
                        <p class="text-white-50 mb-0">
                            Interromper atendimento toda hora derruba qualidade e vendas. A agenda online trabalha por você 24h.
                        </p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5" data-aos="fade-up">
                <a href="#preco" class="btn btn-main btn-lg px-5">
                    Quero organizar meu salão <i class="fa-solid fa-arrow-right ms-2"></i>
                </a>
                <div class="text-white-50 small mt-3">Sem fidelidade • Suporte humano • Comece em minutos</div>
            </div>
        </div>
    </section>

    <!-- COMO FUNCIONA (PASSOS) -->
    <section class="section">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-5" data-aos="fade-right">
                    <div class="text-primary fw-bold text-uppercase small ls-1">Como funciona</div>
                    <h2 class="display-6 fw-bold mt-2 mb-3">3 passos pra virar <span class="text-gradient">gestão profissional</span></h2>
                    <p class="text-secondary fs-5">
                        Você não precisa ser “bom de planilha”. O sistema te guia.
                    </p>
                    <div class="mt-4 d-flex flex-column gap-3">
                        <div class="d-flex gap-3 align-items-start">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:38px;height:38px;background:#eef2ff;color:var(--primary);font-weight:900;">1</div>
                            <div>
                                <div class="fw-bold">Cadastre serviços e profissionais</div>
                                <div class="muted">Defina tempo, preço, comissão e variações.</div>
                            </div>
                        </div>
                        <div class="d-flex gap-3 align-items-start">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:38px;height:38px;background:#eef2ff;color:var(--primary);font-weight:900;">2</div>
                            <div>
                                <div class="fw-bold">Controle estoque por gramas/ml</div>
                                <div class="muted">O sistema converte e desconta automaticamente por atendimento.</div>
                            </div>
                        </div>
                        <div class="d-flex gap-3 align-items-start">
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                 style="width:38px;height:38px;background:#eef2ff;color:var(--primary);font-weight:900;">3</div>
                            <div>
                                <div class="fw-bold">Venda e acompanhe o lucro</div>
                                <div class="muted">Relatórios simples, caixa, comissão, recibos e histórico.</div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a target="_blank" rel="noopener"
                           href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>"
                           class="btn btn-soft">
                            Quero uma demonstração <i class="fa-brands fa-whatsapp ms-2 text-success"></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-7" data-aos="fade-left">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature">
                                <i class="fa-solid fa-calendar-check mb-3"></i>
                                <h4 class="fw-bold mt-2">Agenda inteligente</h4>
                                <p class="text-secondary small mb-0">
                                    Visualização diária/semanal, controle de horários, histórico do cliente e fluxo simples.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature">
                                <i class="fa-brands fa-whatsapp mb-3"></i>
                                <h4 class="fw-bold mt-2">Confirmação no Whats</h4>
                                <p class="text-secondary small mb-0">
                                    Clique em confirmar e já abre a mensagem pronta pro cliente. Menos furos, mais presença.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature">
                                <i class="fa-solid fa-boxes-stacked mb-3"></i>
                                <h4 class="fw-bold mt-2">Estoque fracionado</h4>
                                <p class="text-secondary small mb-0">
                                    Controle por ml/g e validade. Alerta de reposição e desperdício reduzido.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature">
                                <i class="fa-solid fa-chart-line mb-3"></i>
                                <h4 class="fw-bold mt-2">Financeiro e relatórios</h4>
                                <p class="text-secondary small mb-0">
                                    Entradas/saídas, comissão, lucro por serviço e visão clara do mês.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 p-4 rounded-4 bg-white border shadow-soft" data-aos="fade-up">
                        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
                            <div>
                                <div class="fw-bold">Quer ver funcionando no seu caso?</div>
                                <div class="text-secondary small">Me chama no WhatsApp e eu te mostro em 3 minutos.</div>
                            </div>
                            <a target="_blank" rel="noopener"
                               href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>"
                               class="btn btn-main">
                                Pedir demo agora <i class="fa-solid fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>

    <!-- PROVA / RESULTADOS -->
    <section id="prova" class="section">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-6 fw-bold">Resultados que você sente na rotina</h2>
                <p class="text-muted mx-auto" style="max-width: 700px;">
                    Menos caos, menos prejuízo invisível e mais controle — sem complicar.
                </p>
            </div>

            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="0">
                    <div class="t-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-bold">Mais presença</div>
                            <div class="stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                        </div>
                        <div class="text-secondary small">
                            “Só de confirmar pelo WhatsApp e organizar agenda, meus furos reduziram muito.”
                        </div>
                        <div class="mt-3 small text-muted">— Cliente (exemplo)</div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="t-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-bold">Lucro claro</div>
                            <div class="stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                        </div>
                        <div class="text-secondary small">
                            “Eu cobrava errado. Agora sei quanto cada serviço realmente dá de lucro e ajustei preços.”
                        </div>
                        <div class="mt-3 small text-muted">— Cliente (exemplo)</div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="t-card">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="fw-bold">Menos estresse</div>
                            <div class="stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                        </div>
                        <div class="text-secondary small">
                            “Parei de responder ‘tem horário?’ toda hora. Agora o link da agenda filtra muita coisa.”
                        </div>
                        <div class="mt-3 small text-muted">— Cliente (exemplo)</div>
                    </div>
                </div>
            </div>

            <!-- Comparativo -->
            <div class="row gy-4 align-items-center mt-5">
                <div class="col-lg-5" data-aos="fade-right">
                    <div class="text-primary fw-bold text-uppercase small ls-1">Comparação</div>
                    <h3 class="fw-bold mt-2">O que acontece sem sistema vs com Develoi</h3>
                    <p class="text-secondary">
                        O problema não é falta de trabalho. É falta de controle.
                    </p>
                    <a href="#preco" class="btn btn-main">Quero esse controle <i class="fa-solid fa-arrow-right ms-2"></i></a>
                </div>
                <div class="col-lg-7" data-aos="fade-left">
                    <div class="compare">
                        <div class="head p-3">
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Item</span>
                                <span>Develoi</span>
                            </div>
                        </div>
                        <div class="rowx">
                            <span>Agenda organizada e histórico de cliente</span>
                            <span class="pill-ok">Incluído</span>
                        </div>
                        <div class="rowx">
                            <span>Confirmação rápida pelo WhatsApp</span>
                            <span class="pill-ok">Incluído</span>
                        </div>
                        <div class="rowx">
                            <span>Estoque fracionado (ml/g) + alertas</span>
                            <span class="pill-ok">Incluído</span>
                        </div>
                        <div class="rowx">
                            <span>Financeiro simples + relatórios</span>
                            <span class="pill-ok">Incluído</span>
                        </div>
                        <div class="rowx">
                            <span>Sem planilha / sem “chutômetro”</span>
                            <span class="pill-ok">Incluído</span>
                        </div>
                        <div class="rowx">
                            <span>Sem fidelidade (cancele quando quiser)</span>
                            <span class="pill-ok">Incluído</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- PREÇO -->
    <section id="preco" class="section">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="display-6 fw-bold">Preço simples. Valor gigante.</h2>
                <p class="text-muted mx-auto" style="max-width: 720px;">
                    Um plano único com acesso completo. Sem fidelidade. Com suporte humano.
                </p>
            </div>

            <div class="row justify-content-center g-4">
                <div class="col-lg-7" data-aos="zoom-in">
                    <div class="price-card">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                            <div>
                                <div class="price-tag">
                                    <i class="fa-solid fa-circle-check"></i> Plano Mensal • Acesso completo
                                </div>
                                <h3 class="fw-bold mt-3 mb-1">Develoi Agenda</h3>
                                <div class="text-white-50">Ideal para salão, barbearia e estética</div>
                            </div>
                            <div class="text-end">
                                <div class="display-5 fw-bold mb-0">R$ 69,90</div>
                                <div class="text-white-50">por mês • sem fidelidade</div>
                            </div>
                        </div>

                        <hr style="border-color: rgba(255,255,255,.12);" class="my-4">

                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="fw-bold mb-2">Você recebe:</div>
                                <ul class="list-unstyled check mb-0">
                                    <li><i class="fa-solid fa-check me-2"></i>Agenda completa + histórico</li>
                                    <li><i class="fa-solid fa-check me-2"></i>Confirmação via WhatsApp</li>
                                    <li><i class="fa-solid fa-check me-2"></i>Estoque fracionado (ml/g)</li>
                                    <li><i class="fa-solid fa-check me-2"></i>Financeiro e relatórios</li>
                                    <li><i class="fa-solid fa-check me-2"></i>Recibos profissionais</li>
                                    <li><i class="fa-solid fa-check me-2"></i>Suporte humano</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="fw-bold mb-2">Perfeito pra:</div>
                                <ul class="list-unstyled check mb-0">
                                    <li><i class="fa-solid fa-check me-2"></i>Quem quer parar de “chutar” custo</li>
                                    <li><i class="fa-solid fa-check me-2"></i>Quem quer reduzir furos</li>
                                    <li><i class="fa-solid fa-check me-2"></i>Quem quer tempo livre</li>
                                    <li><i class="fa-solid fa-check me-2"></i>Quem quer crescer com controle</li>
                                </ul>
                            </div>
                        </div>

                        <div class="d-flex flex-column flex-md-row gap-3 mt-4">
                            <a target="_blank" rel="noopener"
                               href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_compra); ?>"
                               class="btn btn-light rounded-pill px-5 py-3 fw-bold text-dark w-100">
                                Assinar agora <i class="fa-solid fa-arrow-right ms-2"></i>
                            </a>
                            <a target="_blank" rel="noopener"
                               href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>"
                               class="btn btn-outline-light rounded-pill px-5 py-3 fw-bold w-100">
                                Quero uma demo <i class="fa-brands fa-whatsapp ms-2"></i>
                            </a>
                        </div>

                        <!-- Urgência / bônus -->
                        <div class="mt-4 p-3 rounded-4" style="background: rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12);">
                            <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold">Bônus (opcional): implantação rápida</div>
                                    <div class="text-white-50 small">Se você chamar no WhatsApp, te ajudo a configurar o básico mais rápido.</div>
                                </div>
                                <div class="text-white-50 small">
                                    <i class="fa-solid fa-clock me-2"></i>
                                    <span id="timerText">Oferta de suporte rápido hoje</span>
                                </div>
                            </div>
                        </div>

                        <div class="text-center small text-white-50 mt-3">
                            <i class="fa-solid fa-shield-halved me-2"></i>
                            Cancele quando quiser. Sem multa. Sem burocracia.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- GARANTIA / SEGURANÇA -->
    <section class="section">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-5" data-aos="fade-right">
                    <div class="text-primary fw-bold text-uppercase small ls-1">Segurança</div>
                    <h2 class="fw-bold mt-2">Você não fica preso.</h2>
                    <p class="text-secondary fs-5">
                        Aqui é simples: você usa enquanto fizer sentido. Se quiser parar, cancela sem dor de cabeça.
                    </p>
                </div>
                <div class="col-lg-7" data-aos="fade-left">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="feature">
                                <i class="fa-solid fa-handshake mb-3"></i>
                                <h4 class="fw-bold mt-2">Sem fidelidade</h4>
                                <p class="text-secondary small mb-0">Sem contrato amarrado. Liberdade total.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature">
                                <i class="fa-solid fa-headset mb-3"></i>
                                <h4 class="fw-bold mt-2">Suporte humano</h4>
                                <p class="text-secondary small mb-0">Quando precisar, tem gente de verdade pra ajudar.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature">
                                <i class="fa-solid fa-wand-magic-sparkles mb-3"></i>
                                <h4 class="fw-bold mt-2">Simples de usar</h4>
                                <p class="text-secondary small mb-0">Feito pra rotina real: rápido, direto e intuitivo.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="feature">
                                <i class="fa-solid fa-mobile-screen-button mb-3"></i>
                                <h4 class="fw-bold mt-2">Celular & PC</h4>
                                <p class="text-secondary small mb-0">Funciona no navegador, leve e responsivo.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="section">
        <div class="container">
            <div class="text-center mb-5" data-aos="fade-up">
                <h2 class="fw-bold">Dúvidas Frequentes</h2>
                <p class="text-muted mx-auto" style="max-width: 700px;">
                    Se ficar qualquer dúvida, chama no WhatsApp e eu te ajudo.
                </p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item" data-aos="fade-up">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
                                    Preciso baixar aplicativo?
                                </button>
                            </h2>
                            <div id="q1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Não. O Develoi roda direto no navegador do celular ou do computador. Leve e rápido.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="50">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
                                    Posso cancelar quando quiser?
                                </button>
                            </h2>
                            <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Sim. Sem fidelidade e sem multa. É só avisar pelo WhatsApp.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="100">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
                                    O que está incluso no plano de R$ 69,90/mês?
                                </button>
                            </h2>
                            <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Acesso completo ao sistema: agenda, clientes, estoque fracionado, financeiro, relatórios, recibos e suporte humano.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="150">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q4">
                                    Serve para barbearia com vários profissionais?
                                </button>
                            </h2>
                            <div id="q4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Sim. Você pode cadastrar profissionais, definir comissões diferentes e controlar atendimentos por pessoa.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="200">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5">
                                    Como funciona a confirmação no WhatsApp?
                                </button>
                            </h2>
                            <div id="q5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Você clica em confirmar e o sistema abre o WhatsApp do cliente com a mensagem pronta. Você só envia.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item" data-aos="fade-up" data-aos-delay="250">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q6">
                                    Se eu não sou bom com tecnologia, consigo usar?
                                </button>
                            </h2>
                            <div id="q6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Consegue sim. A proposta é ser simples. E se precisar, o suporte humano te guia.
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="text-center mt-5" data-aos="fade-up">
                        <a target="_blank" rel="noopener"
                           href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>"
                           class="btn btn-main btn-lg px-5">
                            Ainda tenho dúvida — WhatsApp <i class="fa-brands fa-whatsapp ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Final -->
    <div class="container pb-5">
        <div class="cta" data-aos="zoom-in">
            <div class="cta-blob" style="top:-45%; left:-45%; background: var(--primary);"></div>
            <div class="cta-blob" style="bottom:-45%; right:-45%; background: var(--secondary);"></div>

            <div class="position-relative" style="z-index:2;">
                <h2 class="fw-bold display-6 mb-2">Assine o Develoi Agenda agora.</h2>
                <p class="fs-5 opacity-75 mb-4 mx-auto" style="max-width: 680px;">
                    Plano mensal de R$ 69,90 com acesso completo e suporte humano. Sem fidelidade. Sem dor de cabeça.
                </p>

                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a target="_blank" rel="noopener"
                       href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_compra); ?>"
                       class="btn btn-light rounded-pill px-5 py-3 fw-bold text-primary shadow-lg">
                        Assinar por R$ 69,90/mês <i class="fa-solid fa-arrow-right ms-2"></i>
                    </a>
                    <a target="_blank" rel="noopener"
                       href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>"
                       class="btn btn-outline-light rounded-pill px-5 py-3 fw-bold">
                        Ver demo no WhatsApp <i class="fa-brands fa-whatsapp ms-2"></i>
                    </a>
                </div>

                <p class="small mt-4 opacity-50">
                    © 2025 Develoi • Feito no Brasil
                </p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-5 bg-white border-top">
        <div class="container">
            <div class="row gy-4">
                <div class="col-md-5">
                    <h5 class="fw-bold text-dark mb-2">Develoi Agenda</h5>
                    <p class="text-muted small mb-0">
                        Sistema de gestão inteligente para salão, barbearia e estética. Controle e lucro sem complicar.
                    </p>
                </div>
                <div class="col-md-2 offset-md-1">
                    <h6 class="fw-bold mb-2">Menu</h6>
                    <ul class="list-unstyled small text-muted d-flex flex-column gap-2">
                        <li><a href="#recursos" class="text-reset">Recursos</a></li>
                        <li><a href="#prova" class="text-reset">Resultados</a></li>
                        <li><a href="#preco" class="text-reset">Preço</a></li>
                        <li><a href="#faq" class="text-reset">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold mb-2">Fale com a gente</h6>
                    <a class="btn btn-soft rounded-pill"
                       target="_blank" rel="noopener"
                       href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_whatsapp); ?>">
                        <i class="fa-brands fa-whatsapp me-2 text-success"></i> WhatsApp
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Sticky mobile bar -->
    <div class="sticky-mobile">
        <div class="sticky-inner">
            <div class="d-flex flex-column" style="line-height:1.1;">
                <span class="fw-bold text-success" style="font-size:.95rem;">R$ 69,90 <small class="text-white">/mês</small></span>
                <span class="small text-white-50" style="font-size:.7rem;">Acesso completo • Sem fidelidade</span>
            </div>
            <a target="_blank" rel="noopener"
               href="https://wa.me/<?php echo $meu_whatsapp; ?>?text=<?php echo urlencode($mensagem_compra); ?>"
               class="btn btn-primary rounded-pill fw-bold px-4">
                Assinar <i class="fa-solid fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>

    <!-- Chat -->
    <div class="chat-trigger" onclick="toggleChat()">
        <i class="fa-brands fa-whatsapp"></i>
    </div>

    <div class="chat-window" id="chatWindow">
        <div class="chat-header">
            <div class="chat-avatar"><i class="fa-solid fa-robot"></i></div>
            <div>
                <div class="fw-bold" style="font-size: 0.95rem;"><?php echo htmlspecialchars($nome_robo); ?></div>
                <div style="font-size: 0.72rem; opacity: 0.92;">Online agora</div>
            </div>
            <div class="chat-close" onclick="toggleChat()"><i class="fa-solid fa-xmark"></i></div>
        </div>
        <div class="chat-body" id="chatBody"></div>
        <div class="typing-indicator" id="typingIndicator">Digitando...</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // AOS
        AOS.init({
            once: true,
            offset: 60,
            duration: 900,
            easing: 'ease-out-cubic'
        });

        // Navbar scroll
        window.addEventListener('scroll', () => {
            const nav = document.querySelector('.navbar');
            nav.classList.toggle('scrolled', window.scrollY > 50);
        });

        // Mobile menu
        function toggleMenu(){
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('active');
            document.body.classList.toggle('menu-open', menu.classList.contains('active'));
        }

        // Typewriter hero
        const typingEl = document.getElementById('typingText');
        const phrases = ["sua agenda.", "seu salão.", "sua barbearia.", "sua estética.", "seu lucro."];
        let p = 0, c = 0, del = false;

        function typeLoop(){
            const word = phrases[p];
            if(del){ c--; } else { c++; }
            typingEl.textContent = word.substring(0, c);

            let speed = del ? 35 : 85;

            if(!del && c === word.length){
                del = true;
                speed = 1400;
            } else if(del && c === 0){
                del = false;
                p = (p + 1) % phrases.length;
                speed = 350;
            }
            setTimeout(typeLoop, speed);
        }
        document.addEventListener('DOMContentLoaded', typeLoop);

        // Simple timer text (urgência leve, sem data fixa)
        const timerText = document.getElementById('timerText');
        function updateTimerText(){
            const now = new Date();
            const h = now.getHours();
            // só muda mensagem conforme horário
            if(h < 12) timerText.textContent = "Suporte rápido disponível hoje (manhã)";
            else if(h < 18) timerText.textContent = "Suporte rápido disponível hoje (tarde)";
            else timerText.textContent = "Suporte rápido disponível hoje (noite)";
        }
        updateTimerText();

        // Chat
        const whatsappNumber = "<?php echo $meu_whatsapp; ?>";
        const chatBody = document.getElementById('chatBody');
        const chatWindow = document.getElementById('chatWindow');
        const typingInd = document.getElementById('typingIndicator');

        function toggleChat(){
            chatWindow.classList.toggle('active');
            if(chatWindow.classList.contains('active') && chatBody.innerHTML.trim() === ''){
                startConversation();
            }
        }

        function addMessage(text, sender){
            const div = document.createElement('div');
            div.classList.add('msg', sender === 'bot' ? 'msg-bot' : 'msg-user');
            div.innerHTML = text;
            chatBody.appendChild(div);
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        function botType(text, delay = 600){
            typingInd.style.display = 'block';
            setTimeout(() => {
                typingInd.style.display = 'none';
                addMessage(text, 'bot');
            }, delay);
        }

        function showOptions(options){
            setTimeout(() => {
                const wrap = document.createElement('div');
                wrap.classList.add('chat-options');
                options.forEach(opt => {
                    const btn = document.createElement('button');
                    btn.classList.add('btn-option');
                    btn.innerHTML = `${opt.text} <i class="fa-solid fa-chevron-right"></i>`;
                    btn.onclick = () => handleChoice(opt);
                    wrap.appendChild(btn);
                });
                chatBody.appendChild(wrap);
                chatBody.scrollTop = chatBody.scrollHeight;
            }, 500);
        }

        function clearOptions(){
            const old = chatBody.querySelector('.chat-options');
            if(old) old.remove();
        }

        function openWhatsApp(message){
            const url = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
            window.open(url, '_blank');
        }

        function startConversation(){
            botType("Olá! 👋 Eu sou o assistente da Develoi. Quer organizar sua agenda e aumentar seu lucro?");
            setTimeout(() => {
                botType("Me diga o que você quer agora:");
                showOptions([
                    { text: "Quero saber o preço", action: 'price' },
                    { text: "Quero ver como funciona", action: 'how' },
                    { text: "Quero controle de estoque", action: 'stock' },
                    { text: "Quero falar com humano", action: 'human' }
                ]);
            }, 700);
        }

        function handleChoice(opt){
            clearOptions();
            addMessage(opt.text, 'user');

            if(opt.action === 'price'){
                botType("Preço simples: acesso completo por <b>R$ 69,90/mês</b>. Sem fidelidade. ✅");
                setTimeout(() => {
                    botType("Quer que eu abra o WhatsApp para assinar agora?");
                    showOptions([
                        { text: "Sim, quero assinar", action: 'buy' },
                        { text: "Quero uma demonstração", action: 'demo' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 800);
            }

            if(opt.action === 'how'){
                botType("Você controla agenda, clientes, comissões, estoque (ml/g) e financeiro. Tudo pelo celular ou PC.");
                setTimeout(() => {
                    botType("Quer demo rápida no WhatsApp ou já quer assinar?");
                    showOptions([
                        { text: "Quero demo", action: 'demo' },
                        { text: "Quero assinar", action: 'buy' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 900);
            }

            if(opt.action === 'stock'){
                botType("O sistema desconta automaticamente a quantidade usada por serviço (gramas/ml). Você para de perder dinheiro no 'chutômetro'.");
                setTimeout(() => {
                    botType("Quer ver isso funcionando numa demo?");
                    showOptions([
                        { text: "Quero demo", action: 'demo' },
                        { text: "Quero assinar", action: 'buy' },
                        { text: "Voltar ao menu", action: 'restart' }
                    ]);
                }, 900);
            }

            if(opt.action === 'human'){
                botType("Perfeito! Vou abrir seu WhatsApp para falar com nosso time.");
                setTimeout(() => openWhatsApp("Olá! Preciso tirar uma dúvida com o suporte da Develoi."), 900);
                setTimeout(() => {
                    botType("Se precisar de mais alguma coisa, é só me chamar aqui. 😊");
                    showOptions([{ text: "Voltar ao menu", action: 'restart' }]);
                }, 1400);
            }

            if(opt.action === 'demo'){
                botType("Ótima! Vou abrir seu WhatsApp para você pedir a demonstração.");
                setTimeout(() => openWhatsApp("Olá! Quero solicitar uma demonstração do Develoi Agenda."), 900);
                setTimeout(() => {
                    botType("Depois da demo, se quiser, eu te ajudo a assinar.");
                    showOptions([{ text: "Voltar ao menu", action: 'restart' }]);
                }, 1400);
            }

            if(opt.action === 'buy'){
                botType("Top! Vou abrir seu WhatsApp para finalizar a assinatura do plano de R$ 69,90/mês.");
                setTimeout(() => openWhatsApp("Olá! Quero assinar o Develoi Agenda por R$ 69,90/mês. Pode liberar meu acesso?"), 900);
                setTimeout(() => {
                    botType("Fechou! Se precisar, eu também ajudo na configuração inicial.");
                    showOptions([{ text: "Voltar ao menu", action: 'restart' }]);
                }, 1400);
            }

            if(opt.action === 'restart'){
                chatBody.innerHTML = '';
                startConversation();
            }
        }
    </script>
</body>
</html>
