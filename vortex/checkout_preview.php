<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vortex Payment Checkout (Preview)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #eef2f9;

            --card-navy: #0e1643;
            --card-blue: #2c54f5;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --text-dark: #1e293b;
            --accent-purple: #6366f1;
            --accent-cyan: #06b6d4;
            --accent-emerald: #10b981;
            --accent-rose: #f43f5e;
            --font-main: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-page);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(200, 220, 255, 0.6) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(220, 230, 255, 0.6) 0%, transparent 50%);
            color: var(--text-dark);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .checkout-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 540px;
            gap: 1.5rem;
        }

        .checkout-card {
            width: 100%;
            max-width: 540px;
            background-color: var(--card-navy);
            border-radius: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(14, 23, 62, 0.2);
            padding: 2.25rem 2rem;
            color: white;
            z-index: 10;
            animation: cardSpringIn 1.2s cubic-bezier(0.25, 1.3, 0.5, 1) forwards;
        }

        @keyframes cardSpringIn {
            0% {
                transform: translateY(-80px);
                opacity: 0;
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .card-bg-shape {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            background-size: cover;
            background-position: center;
            transform: translateY(-100%);
            opacity: 0;
        }

        .shape-1 {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 500 800' preserveAspectRatio='none'%3E%3Cpath d='M500,0 L0,0 L0,300 C150,550 350,150 500,400 Z' fill='%230A1938' opacity='0.8'/%3E%3C/svg%3E");
            animation: slideDownShape 1.2s cubic-bezier(0.22, 1, 0.36, 1) 0.3s forwards;
        }

        .shape-2 {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 500 800' preserveAspectRatio='none'%3E%3Cpath d='M500,0 L0,0 L0,200 C200,400 300,50 500,250 Z' fill='%23133989' opacity='0.9'/%3E%3C/svg%3E");
            animation: slideDownShape 1.3s cubic-bezier(0.22, 1, 0.36, 1) 0.5s forwards;
        }

        @keyframes slideDownShape {
            0% {
                transform: translateY(-100%) scaleY(1.2);
                opacity: 0;
            }
            100% {
                transform: translateY(0) scaleY(1);
                opacity: 1;
            }
        }

        .card-content {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-brand {
            display: flex;
            align-items: center;
            gap: 0;
        }

        .brand-logo {
            width: 28px;
            height: 28px;
            background: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--card-navy);
            font-weight: 800;
            font-size: 1rem;
            transform: rotate(-10deg);
        }

        .brand-name {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .timer-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            border-radius: 12px;
            padding: 0.4rem 0.75rem;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            color: #fca5a5;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .timer-badge.safe {
            color: #86efac;
        }

        .balance-section {
            margin-top: 0.5rem;
        }

        .balance-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #ffffff;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .balance-amount {
            font-size: 2.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            letter-spacing: -0.02em;
        }

        .currency-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: #ffffff;
            margin-top: 1rem;
        }

        .details-container {
            background: rgba(0, 0, 0, 0.15);
            border-radius: 16px;
            padding: 1.25rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 0.95rem;
        }

        .detail-row:last-of-type {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-row:first-of-type {
            padding-top: 0;
        }

        .detail-label {
            color: #ffffff;
            font-weight: 500;
        }

        .detail-value {
            font-weight: 700;
            color: #ffffff;
            text-align: right;
        }

        .btn-pay {
            width: 100%;
            background: white;
            color: var(--card-navy);
            border: none;
            border-radius: 14px;
            padding: 1.1rem 1.5rem;
            font-family: var(--font-main);
            font-size: 1.15rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.25s ease;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            margin-top: 0.5rem;
            position: relative;
            overflow: hidden;
        }

        .btn-pay::after {
            content: '';
            position: absolute;
            top: 0;
            left: -150%;
            width: 80%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(44, 84, 245, 0.5), transparent);
            transform: skewX(-25deg);
            animation: shinySweep 2s ease-in-out 1s forwards;
            pointer-events: none;
        }

        @keyframes shinySweep {
            0% { left: -150%; }
            100% { left: 200%; }
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.25);
            background: #f8fafc;
        }

        .btn-pay:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-back {
            align-self: flex-start;
            background: transparent;
            color: var(--text-dark);
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0.5rem 1rem;
            font-family: var(--font-main);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
            text-decoration: none;
        }
        .btn-back:hover {
            background: #f1f5f9;
        }
    </style>
</head>
<body>

<div class="checkout-wrapper">
    <a href="javascript:history.back()" class="btn-back">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back
    </a>

    <!-- The Single Checkout Card -->
    <div class="checkout-card">
        <div class="card-bg-shape shape-1"></div>
        <div class="card-bg-shape shape-2"></div>
        
        <div class="card-content">
            <div class="card-header">
                <div class="card-brand">
                    <img src="https://res.cloudinary.com/dswfp5fwx/image/upload/v1789740193/VortexLogo_ssrpym.png" alt="Vortex Logo" style="height: 38px; width: auto; object-fit: contain; margin-right: -4px;">
                    <div class="brand-name">ORTEX</div>
                </div>
                <div class="timer-badge safe" id="timer-display">
                    ⏱ 5:00
                </div>
            </div>

            <div class="balance-section">
                <div class="balance-label">Total Amount Due</div>
                <div class="balance-amount">
                    ₹600.00
                    <span class="currency-label">INR</span>
                </div>
            </div>

            <div class="details-container">
                <div class="detail-row">
                    <span class="detail-label">Event</span>
                    <span class="detail-value">ICSWHMH 2027</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">114akb@gmail.com</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Mobile</span>
                    <span class="detail-value">8921315216</span>
                </div>
            </div>

            <button class="btn-pay" id="pay-btn">
                <span>Pay ₹600.00</span>
            </button>
        </div>
    </div>
</div>
</body>
</html>
