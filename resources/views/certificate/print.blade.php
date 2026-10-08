<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Employment - {{ $data['student_name'] }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Great+Vibes&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
            background: #F8FAFC;
            color: #1E293B;
            padding: 30px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .action-bar {
            width: 100%;
            max-width: 820px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-primary {
            background: #DC2626;
            color: #FFFFFF;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
        }
        .btn-primary:hover {
            background: #B91C1C;
        }
        .cert-card {
            width: 100%;
            max-width: 820px;
            background: #FFFFFF;
            border: 2px solid #E2E8F0;
            border-radius: 20px;
            padding: 50px 55px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            position: relative;
            overflow: hidden;
        }
        .cert-border-outer {
            border: 3px double #DC2626;
            padding: 35px 40px;
            border-radius: 14px;
            position: relative;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-25deg);
            font-size: 80px;
            font-weight: 900;
            color: rgba(220, 38, 38, 0.03);
            letter-spacing: 12px;
            pointer-events: none;
            white-space: nowrap;
            user-select: none;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .app-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FEF2F2;
            color: #DC2626;
            padding: 4px 14px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            border: 1px solid #FEE2E2;
            margin-bottom: 12px;
        }
        .cert-title {
            font-family: 'Cinzel', serif;
            font-size: 26px;
            font-weight: 900;
            letter-spacing: 2px;
            color: #0F172A;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .cert-subtitle {
            font-size: 13px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .cert-body {
            font-size: 14.5px;
            line-height: 1.85;
            color: #334155;
            text-align: justify;
            margin-bottom: 30px;
        }
        .student-name {
            font-size: 22px;
            font-weight: 800;
            color: #0F172A;
            text-decoration: underline;
            text-underline-offset: 4px;
            text-decoration-color: #DC2626;
            display: inline-block;
        }
        .highlight {
            font-weight: 700;
            color: #0F172A;
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 18px 24px;
            margin: 25px 0 35px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-size: 10.5px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 2px;
        }
        .detail-value {
            font-size: 13.5px;
            font-weight: 700;
            color: #0F172A;
        }
        .signatures {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 40px;
            padding-top: 20px;
        }
        .signature-box {
            text-align: center;
            min-width: 200px;
        }
        .handwritten-sign {
            font-family: 'Great Vibes', cursive;
            font-size: 32px;
            color: #0F172A;
            margin-bottom: -10px;
        }
        .signature-line {
            border-top: 1.5px solid #0F172A;
            padding-top: 6px;
            font-size: 13px;
            font-weight: 700;
            color: #0F172A;
        }
        .signature-role {
            font-size: 11px;
            color: #64748B;
            font-weight: 600;
        }
        .seal-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .seal-stamp {
            width: 75px;
            height: 75px;
            border-radius: 50%;
            border: 2px dashed #DC2626;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #DC2626;
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            text-align: center;
            transform: rotate(-10deg);
            background: #FEF2F2;
        }
        .footer-note {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #94A3B8;
            letter-spacing: 0.5px;
        }
        @media print {
            body {
                background: #FFFFFF;
                padding: 0;
            }
            .action-bar {
                display: none !important;
            }
            .cert-card {
                border: none;
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
            }
            .cert-border-outer {
                border-width: 2px;
            }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <a href="javascript:window.close()" class="btn" style="background: #E2E8F0; color: #334155;">&larr; Back</a>
        <button onclick="window.print()" class="btn btn-primary">
            🖨️ Download / Save as PDF
        </button>
    </div>

    <div class="cert-card">
        <div class="watermark">DISKARTECH VERIFIED</div>
        
        <div class="cert-border-outer">
            <div class="header">
                <div class="app-badge">Official DiskarTech Working Student Certification</div>
                <h1 class="cert-title">Certificate of Employment</h1>
                <p class="cert-subtitle">Working Student Engagement Verification</p>
            </div>

            <div class="cert-body">
                This is to certify that <span class="student-name">{{ $data['student_name'] }}</span>, a bonafide student of <span class="highlight">{{ $data['student_school'] }}</span> taking up <span class="highlight">{{ $data['student_course'] }}</span>, is officially recognized as an active and registered working student under <span class="highlight">{{ $data['company_name'] }}</span> via the <strong>DiskarTech Working Student Marketplace Platform</strong>.
                <br><br>
                The said individual has rendered diligent work services in the capacity of <span class="highlight">{{ $data['job_title'] }}</span> ({{ $data['job_category'] }}) from <span class="highlight">{{ $data['start_date'] }}</span> to <span class="highlight">{{ $data['end_date'] }}</span>.
            </div>

            <div class="details-grid">
                <div class="detail-item">
                    <span class="detail-label">Working Schedule</span>
                    <span class="detail-value">{{ $data['work_schedule'] }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Agreed Compensation</span>
                    <span class="detail-value">{{ $data['agreed_rate'] }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Workplace / Office Address</span>
                    <span class="detail-value">{{ $data['company_address'] }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Certificate Reference No.</span>
                    <span class="detail-value" style="color: #DC2626;">{{ $data['certificate_number'] }}</span>
                </div>
            </div>

            <div style="font-size: 13.5px; color: #475569; line-height: 1.7; margin-bottom: 25px;">
                This certification is issued upon the request of the interested party for academic verification, scholarship requirements, school endorsement, or other lawful purposes it may serve.
            </div>

            <div class="signatures">
                <div class="signature-box">
                    @if(!empty($data['employer_signature']))
                        <div style="height: 48px; display: flex; align-items: flex-end; justify-content: center; margin-bottom: 4px;">
                            <img src="{{ $data['employer_signature'] }}" alt="Digitally Signed" style="max-height: 48px; max-width: 170px; object-fit: contain;" />
                        </div>
                    @else
                        <div class="handwritten-sign">{{ $data['hirer_name'] }}</div>
                    @endif
                    <div class="signature-line">{{ $data['hirer_name'] }}</div>
                    <div class="signature-role">Employer</div>
                    <div style="font-size: 10px; color: #94A3B8; margin-top: 3px;">Date: {{ $data['issued_date'] }}</div>
                </div>

                <div class="seal-box">
                    <div class="seal-stamp">
                        <span>DISKARTECH</span>
                        <span style="font-size: 7.5px; margin: 2px 0;">OFFICIALLY</span>
                        <span>VERIFIED</span>
                    </div>
                </div>

                <div class="signature-box">
                    <div class="handwritten-sign" style="color: #DC2626;">DiskarTech Admin</div>
                    <div class="signature-line">DISKARTECH ACADEMIC LIASON</div>
                    <div class="signature-role">Platform Verification Seal</div>
                    <div style="font-size: 10px; color: #94A3B8; margin-top: 3px;">Status: Verified Active</div>
                </div>
            </div>

            <div class="footer-note">
                Reference ID: {{ $data['certificate_number'] }} • Electronically Generated via DiskarTech System
            </div>
        </div>
    </div>

</body>
</html>
