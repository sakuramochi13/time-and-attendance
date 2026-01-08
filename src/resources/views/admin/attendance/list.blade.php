@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance_list.css')}}">
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
        <h1 class="attendance-list__title">{{ $currentDate->isoFormat('YYYY年M月D日') }}の勤怠</h1>
        <div class="attendance-list__calendar">
            <a class="list__calendar-before"
                href="{{ route('admin.attendance.list', ['date' => $prevDate->format('Y-m-d')]) }}">
                <img src="{{ asset('images/arrow_left_fill.svg') }}" class="icon-arrow">
                前日
            </a>
            <span class="list__calendar-center" id="calendar-wrapper">
                <img src="{{ asset('images/icon_125550.svg') }}" class="icon-calendar">
                <span id="calendar-label">
                    {{ $currentDate->format('Y/m/d') }}
                </span>
                <input
                    type="text"
                    id="calendar-input"
                    value="{{ $currentDate->format('Y-m-d') }}"
                    data-input>
            </span>
            <a class="list__calendar-after"
            href="{{ route('admin.attendance.list', ['date' => $nextDate->format('Y-m-d')]) }}">
                翌日
                <img src="{{ asset('images/arrow_right_fill.svg') }}" class="icon-arrow">
            </a>
        </div>
        <table class="attendance-list__table">
            <thead class="attendance-list__table-title">
                <tr>
                    <th class="attendance-list__col--name">名前</th>
                    <th class="attendance-list__col--check-in">出勤</th>
                    <th class="attendance-list__col--check-out">退勤</th>
                    <th class="attendance-list__col--break">休憩</th>
                    <th class="attendance-list__col--total">合計</th>
                    <th class="attendance-list__col--detail">詳細</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($staff as $user)
                <tr class="attendance-list__table-cell">
                    <td>
                        {{ $user->name }}
                    </td>
                    <td>
                        {{ optional($user->attendances->first()?->clock_in_at)->format('H:i') }}
                    </td>
                    <td>
                        {{ optional($user->attendances->first()?->clock_out_at)->format('H:i') }}
                    </td>
                    <td>
                        {{ $user->attendances->first()?->break_duration }}
                    </td>
                    <td>
                        {{ $user->attendances->first()?->working_duration }}
                    </td>
                    <td>
                        <a class="attendance-list__table-cell--detail"
                            href="{{ route('admin.attendance.show.by-date', [
                                'user' => $user->id,
                                'date' => $currentDate->toDateString(),
                            ]) }}">
                            詳細
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; padding:16px;">
                        スタッフが存在しません。
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    const baseUrl = "{{ route('admin.attendance.list') }}";

    const input   = document.getElementById('calendar-input');
    const wrapper = document.getElementById('calendar-wrapper');
    const label   = document.getElementById('calendar-label');

    if (input && wrapper) {
        flatpickr(input, {
            locale: 'ja',
            dateFormat: "Y-m-d",
            defaultDate: input.value,
            positionElement: wrapper,

            onReady: function (selectedDates, dateStr, instance) {
                if (label) label.textContent = instance.formatDate(instance.selectedDates[0], "Y/m/d");
            },

            onChange: function (selectedDates, dateStr, instance) {
                if (!dateStr) return;

                if (label) label.textContent = instance.formatDate(selectedDates[0], "Y/m/d");

                window.location.href = baseUrl + '?date=' + dateStr;
            }
        });
    }
</script>
@endsection




