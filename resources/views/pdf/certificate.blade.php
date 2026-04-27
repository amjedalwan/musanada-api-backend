<!DOCTYPE html>
<html dir="rtl">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
    body {
        /* ضروري جداً لظهور الحروف العربية التي تم معالجتها */
        font-family: 'DejaVu Sans', sans-serif;
        direction: rtl;
        text-align: center;
        padding: 50px;
    }


    * {
        font-family: 'DejaVu Sans', sans-serif;
        direction: rtl;
        text-align: center;
    }

    .title {
        font-size: 30px;
        margin-bottom: 20px;
    }

    .name {
        font-size: 40px;
        color: #2c3e50;
        margin: 20px 0;
    }

    .details {
        font-size: 20px;
        margin: 10px 0;
    }

    .footer {
        margin-top: 50px;
        font-size: 14px;
        color: #7f8c8d;
    }
    </style>
</head>

<body>
    <div class="title">{{ $cert_title }}</div>
    <div class="details">{{ $platform_name }} تشهد بأن المتطوع/ة:</div>
    <div class="name">{{ $full_name }}</div>
    <div class="details">قد أكمل بنجاح الفرصة التطوعية المتميزة بعنوان:</div>
    <div class="details"><strong>{{ $opportunity_title }}</strong></div>
    <div class="details">بإشراف مؤسسة: {{ $organization_name }}</div>

    <div class="footer">
        <div>تاريخ الإصدار: {{ $issue_date }}</div>
        <div>رقم التحقق: {{ $certificate_code }}</div>
    </div>
</body>

</html>