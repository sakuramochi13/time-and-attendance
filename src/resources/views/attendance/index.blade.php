@extends('layouts/app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/user/home.css')}}">
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
    <div class="attendance__container">
        <div class="attendance__status">
            <span class="attendance__status-text">
                {{ $attendance->work_status_label ?? '勤務外' }}
            </span>
        </div>
        <p class="attendance__date">{{ now()->isoFormat('YYYY年M月D日(ddd)') }}</p>
        <p class="attendance__clock" id="js-clock"></p>
        @if(is_null($attendance) || $attendance->isWorkOff())
        <div>
            <form class="form-attendance"
                action="{{ route('attendance.clock_in') }}" method="POST">
                @csrf
                <button class="attendance__button work" type="submit">出勤</button>
            </form>
        </div>
        @elseif($attendance->isWorking())
        <div class="attendance__section">
            <form class="form-attendance"
                action="{{ route('attendance.clock_out') }}" method="POST">
                @csrf
                <button class="attendance__button work" type="submit">退勤</button>
            </form>
            <form class="form-attendance"
                action="{{ route('attendance.break_start') }}" method="POST">
                @csrf
                <button class="attendance__button break" type="submit">休憩入</button>
            </form>
        </div>
        @elseif($attendance->isOnBreak())
        <div>
            <form class="form-attendance"
                action="{{ route('attendance.break_end') }}" method="POST">
                @csrf
                <button class="attendance__button break" type="submit">休憩戻</button>
            </form>
        </div>
        @elseif($attendance->isFinished())
        <div class="attendance__clock-out">
            <p class="attendance__clock-out--message">お疲れ様でした。</p>
        </div>
        @endif
    </div>
</div>

<script>
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('js-clock').textContent = `${h}:${m}`;
}
setInterval(updateClock, 1000);
updateClock();
</script>
@endsection