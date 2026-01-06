<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reminder Kontrak</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: {{ $daysRemaining <= 0 ? '#fee2e2' : ($daysRemaining <= 30 ? '#fef3c7' : '#d1fae5') }}; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h1 style="margin: 0 0 10px 0; color: {{ $daysRemaining <= 0 ? '#991b1b' : ($daysRemaining <= 30 ? '#92400e' : '#065f46') }};">
            @if($daysRemaining <= 0)
                ⚠️ Kontrak Sudah Expired
            @elseif($daysRemaining <= 30)
                🔴 Kontrak Akan Segera Expired
            @else
                🟡 Reminder Kontrak
            @endif
        </h1>
        <p style="margin: 0; font-size: 18px;">
            @if($daysRemaining <= 0)
                Kontrak ini sudah melewati tanggal berakhir.
            @else
                Kontrak ini akan berakhir dalam <strong>{{ $daysRemaining }} hari</strong>.
            @endif
        </p>
    </div>

    <div style="background: #f9fafb; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h2 style="margin: 0 0 15px 0; font-size: 16px; color: #6b7280; text-transform: uppercase;">Detail Kontrak</h2>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; width: 40%;">Nomor Kontrak</td>
                <td style="padding: 8px 0; font-weight: bold;">{{ $contract->contract_number }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Partner</td>
                <td style="padding: 8px 0;">{{ $contract->partner->display_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Divisi</td>
                <td style="padding: 8px 0;">{{ $contract->division->name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">PIC</td>
                <td style="padding: 8px 0;">{{ $contract->pic->name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Tanggal Mulai</td>
                <td style="padding: 8px 0;">{{ $contract->start_date->format('d F Y') }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Tanggal Berakhir</td>
                <td style="padding: 8px 0; font-weight: bold; color: {{ $daysRemaining <= 0 ? '#dc2626' : '#000' }};">{{ $contract->end_date->format('d F Y') }}</td>
            </tr>
            @if($contract->description)
            <tr>
                <td style="padding: 8px 0; color: #6b7280; vertical-align: top;">Deskripsi</td>
                <td style="padding: 8px 0;">{{ $contract->description }}</td>
            </tr>
            @endif
        </table>
    </div>

    <div style="text-align: center; margin: 20px 0;">
        <a href="{{ url('/contracts/' . $contract->id) }}" style="display: inline-block; background: #2563eb; color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;">
            Lihat Detail Kontrak
        </a>
    </div>

    <div style="border-top: 1px solid #e5e7eb; padding-top: 20px; margin-top: 20px; font-size: 12px; color: #6b7280;">
        <p>Email ini dikirim otomatis oleh sistem PKS Tracking.</p>
        <p>Jika Anda tidak ingin menerima email ini, silakan hubungi administrator.</p>
    </div>
</body>
</html>
