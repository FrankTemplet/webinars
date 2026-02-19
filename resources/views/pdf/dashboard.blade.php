<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #4F46E5;
            margin: 0;
            font-size: 24px;
        }
        .filters {
            background-color: #f3f4f6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .filters h2 {
            margin-top: 0;
            font-size: 16px;
            color: #4F46E5;
        }
        .filter-item {
            margin: 5px 0;
        }
        .stats-grid {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .stat-row {
            display: table-row;
        }
        .stat-card {
            display: table-cell;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            padding: 15px;
            margin: 5px;
            border-radius: 5px;
            width: 33%;
        }
        .stat-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 5px;
        }
        .stat-description {
            font-size: 10px;
            color: #9ca3af;
        }
        .chart-section {
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .chart-title {
            font-size: 16px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 10px;
            border-left: 4px solid #4F46E5;
            padding-left: 10px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th {
            background-color: #4F46E5;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        .data-table td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            font-size: 11px;
        }
        .data-table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dashboard Report</h1>
        <p>Generated on {{ now()->format('F d, Y \a\t H:i') }}</p>
    </div>

    <div class="filters">
        <h2>Applied Filters</h2>
        <div class="filter-item"><strong>Date Range:</strong> {{ $filters['startDate'] ?? 'N/A' }} to {{ $filters['endDate'] ?? 'N/A' }}</div>
        <div class="filter-item"><strong>Client:</strong> {{ $filters['clientName'] ?? 'All clients' }}</div>
        <div class="filter-item"><strong>Webinar:</strong> {{ $filters['webinarTitle'] ?? 'All webinars' }}</div>
    </div>

    <h2 style="color: #4F46E5; margin-bottom: 15px;">Statistics Overview</h2>
    <div class="stats-grid">
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-label">Total Registers</div>
                <div class="stat-value">{{ number_format($stats['totalSubmissions']) }}</div>
                <div class="stat-description">Total submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Register Contacts</div>
                <div class="stat-value">{{ number_format($stats['submissionUtmBlanks']) }}</div>
                <div class="stat-description">Blanks UTM fields</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Webinar Attendance</div>
                <div class="stat-value">{{ number_format($stats['webinarAttendance']) }}</div>
                <div class="stat-description">Registered attendance</div>
            </div>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-row">
            <div class="stat-card">
                <div class="stat-label">Leads</div>
                <div class="stat-value">{{ number_format($stats['registeredLeads']) }}</div>
                <div class="stat-description">Leads registered</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Clients</div>
                <div class="stat-value">{{ number_format($stats['totalClients']) }}</div>
                <div class="stat-description">Total of clients</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Webinars</div>
                <div class="stat-value">{{ number_format($stats['totalWebinars']) }}</div>
                <div class="stat-description">Total webinars</div>
            </div>
        </div>
    </div>

    <div class="page-break"></div>

    @if(!empty($charts['employeeRange']))
    <div class="chart-section">
        <div class="chart-title">Registers by Employee Range</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee Range</th>
                    <th>Number of Registers</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $total = array_sum($charts['employeeRange']);
                @endphp
                @foreach($charts['employeeRange'] as $range => $count)
                <tr>
                    <td>{{ $range }}</td>
                    <td>{{ number_format($count) }}</td>
                    <td>{{ $total > 0 ? number_format(($count / $total) * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!empty($charts['country']))
    <div class="chart-section">
        <div class="chart-title">Registers by Country</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Country</th>
                    <th>Number of Registers</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $total = array_sum($charts['country']);
                @endphp
                @foreach($charts['country'] as $country => $count)
                <tr>
                    <td>{{ $country }}</td>
                    <td>{{ number_format($count) }}</td>
                    <td>{{ $total > 0 ? number_format(($count / $total) * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(!empty($charts['utmMedium']))
    <div class="chart-section">
        <div class="chart-title">Registers per Channel (UTM Medium)</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Channel</th>
                    <th>Number of Registers</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $total = array_sum($charts['utmMedium']);
                @endphp
                @foreach($charts['utmMedium'] as $medium => $count)
                <tr>
                    <td>{{ $medium }}</td>
                    <td>{{ number_format($count) }}</td>
                    <td>{{ $total > 0 ? number_format(($count / $total) * 100, 1) : 0 }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if(isset($charts['paidVsOrganic']))
    <div class="chart-section">
        <div class="chart-title">Paid vs Organic Traffic</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Source Type</th>
                    <th>Number of Registers</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $total = $charts['paidVsOrganic']['paid'] + $charts['paidVsOrganic']['organic'];
                @endphp
                <tr>
                    <td>Paid</td>
                    <td>{{ number_format($charts['paidVsOrganic']['paid']) }}</td>
                    <td>{{ $total > 0 ? number_format(($charts['paidVsOrganic']['paid'] / $total) * 100, 1) : 0 }}%</td>
                </tr>
                <tr>
                    <td>Organic</td>
                    <td>{{ number_format($charts['paidVsOrganic']['organic']) }}</td>
                    <td>{{ $total > 0 ? number_format(($charts['paidVsOrganic']['organic'] / $total) * 100, 1) : 0 }}%</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>This report was generated automatically from the Webinars Dashboard System</p>
    </div>
</body>
</html>
