@extends('layouts/app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance_staff.css')}}">
@endsection

@section('header_nav')
    @auth
        @if (Auth::user()->hasRole('admin'))
            @include('partials.admin-header-nav')
        @else
            @include('partials.user-header-nav')
        @endif
    @endauth
@endsection

@section('content')
<div class="main">
    <div class="attendance-list__container">
        <h1 class="attendance-list__title">{{ $user->name }}さんの勤怠</h1>
        <div class="attendance-list__calendar">
            <a class="list__calendar-before"
                href="{{ route('admin.attendance.staff.show', ['user' => $user->id, 'month' => $prevMonth->format('Y-m')]) }}">
                <img src="{{ asset('images/arrow_left_fill.svg') }}" class="icon-arrow">
                前月
            </a>
            <span class="list__calendar-center" id="calendar-wrapper">
                <img src="{{ asset('images/icon_125550.svg') }}" class="icon-calendar">
                <span id="calendar-label">
                    {{ $currentMonth->isoFormat('YYYY/M') }}
                </span>
                <input
                    type="text"
                    id="calendar-input"
                    value="{{ $currentMonth->format('Y-m') }}"
                    data-input>
            </span>
            <a class="list__calendar-after"
            href="{{ route('admin.attendance.staff.show', ['user' => $user->id, 'month' => $nextMonth->format('Y-m')]) }}">
                翌月
                <img src="{{ asset('images/arrow_right_fill.svg') }}" class="icon-arrow">
            </a>
        </div>
        <table class="attendance-list__table">
            <thead class="attendance-list__table-title">
                <tr>
                    <th class="attendance-list__col--date">日付</th>
                    <th class="attendance-list__col--check-in">出勤</th>
                    <th class="attendance-list__col--check-out">退勤</th>
                    <th class="attendance-list__col--break">休憩</th>
                    <th class="attendance-list__col--total">合計</th>
                    <th class="attendance-list__col--detail">詳細</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($attendances as $attendance)
                <tr class="attendance-list__table-cell">
                    <td>
                        {{ optional($attendance->work_date)->isoFormat('MM/DD(ddd)') }}
                    </td>
                    <td>
                        {{ optional($attendance->clock_in_at)->format('H:i') ?? '' }}
                    </td>
                    <td>
                        {{ optional($attendance->clock_out_at)->format('H:i') ?? '' }}
                    </td>
                    <td>
                        {{ $attendance->break_duration }}
                    </td>
                    <td>
                        {{ $attendance->working_duration }}
                    </td>
                    <td><a class="attendance-list__table-cell--detail"
                        href="{{ route('admin.attendance.show', $attendance->id) }}">詳細</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:16px;">
                        この月の勤怠データはありません。
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        <div class="attendance-list__csv">
            <a class="attendance-list__csv-download"
            href="{{ route('admin.attendance.staff.export', ['user' => $user->id, 'month' => $currentMonth->format('Y-m')]) }}">CSV出力</a>
        </div>
    </div>
</div>

<script>
    const baseUrl = "{{ route('admin.attendance.staff.show', ['user' => $user->id]) }}";

    const input   = document.getElementById('calendar-input');
    const wrapper = document.getElementById('calendar-wrapper');

    if (input && wrapper) {
        flatpickr(input, {
            locale: 'ja',
            plugins: [
                new monthSelectPlugin({
                    shorthand: true,
                    dateFormat: "Y-m",
                    altFormat: "Y年n月",
                })
            ],

            positionElement: wrapper,

            onChange: function (selectedDates, dateStr, instance) {
                if (!dateStr) return;
                window.location.href = baseUrl + '?month=' + dateStr;
            }
        });
    }
</script>
@endsection



