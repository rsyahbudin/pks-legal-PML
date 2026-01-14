<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Models\Setting::get('app_name', 'PKS Tracking System') }}</title>
    <style>
        body {
           font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .email-header {
            padding: 30px 40px;
            text-align: center;
            border-bottom: 2px solid #e5e5e5;
        }
        .email-logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 10px;
        }
        .email-body {
            padding: 40px;
            white-space: pre-line;
            font-size: 14px;
        }
        .email-footer {
            padding: 20px 40px;
            border-top: 1px solid #e5e5e5;
            font-size: 12px;
            color: #666666;
            text-align: center;
        }
        .email-footer p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            @php
                $logoPath = \App\Models\Setting::get('company_logo');
                $logoUrl = null;
                
                if ($logoPath && file_exists(storage_path('app/public/' . $logoPath))) {
                    // Use absolute URL for email compatibility
                    $logoUrl = config('app.url') . '/storage/' . $logoPath;
                }
            @endphp
            
            @if($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ \App\Models\Setting::get('app_name', 'PKS Tracking System') }}" class="email-logo">
            @else
                <h2 style="margin: 0; color: #333333;">{{ \App\Models\Setting::get('app_name', 'PKS Tracking System') }}</h2>
            @endif
        </div>
        
        <div class="email-body">
            {!! nl2br(e($body)) !!}
        </div>
        
        <div class="email-footer">
            <p>Email ini dikirim secara otomatis oleh sistem.</p>
            <p>&copy; {{ date('Y') }} {{ \App\Models\Setting::get('app_name', 'PKS Tracking System') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
