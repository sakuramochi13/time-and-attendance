@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/approve.css')}}">
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
    <div class="attendance-detail__container">
        <h1 class="attendance-detail__title">勤怠詳細</h1>
        <dl class="detail__table">
            <div class="detail__table-top">
                <dt class="detail__table-label">名前</dt>
                <dd class="detail__table-group">
                    <span class="detail__text-name">{{ $attendance->user->name ?? '' }}</span>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            <div class="detail__table-middle">
                <dt class="detail__table-label">日付</dt>
                <dd class="detail__table-group">
                    <span class="detail__text">{{ optional($attendance->work_date)->format('Y年') }}</span>
                    <span class="detail__text-tilde">　</span>
                    <span class="detail__text">{{ optional($attendance->work_date)->format('n月j日') }}</span>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            <div class="detail__table-middle">
                <dt class="detail__table-label">出勤・退勤</dt>
                <dd class="detail__table-group">
                    <input class="detail__text--input-correction" type="text"
                    value="{{ optional($correction->requested_clock_in_at)->format('H:i') ?? '' }}" readonly>
                    <span class="detail__text-tilde">〜</span>
                    <input class="detail__text--input-correction" type="text"
                    value="{{ optional($correction->requested_clock_out_at)->format('H:i') ?? '' }}" readonly>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            @if (($correctionBreaks ?? collect())->isEmpty())
            <div class="detail__table-middle">
                <dt class="detail__table-label">休憩</dt>
                <dd class="detail__table-group">
                    <input class="detail__text--input-correction" type="text" value="" readonly>
                    <span class="detail__text-tilde">〜</span>
                    <input class="detail__text--input-correction" type="text" value="" readonly>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            @else
            @foreach (($correctionBreaks ?? collect()) as $breakIndex => $break)
            <div class="detail__table-middle">
                <dt class="detail__table-label">
                {{ $breakIndex === 0 ? '休憩' : '休憩' . ($breakIndex + 1) }}
                </dt>
                <dd class="detail__table-group">
                    <input class="detail__text--input-correction" type="text"
                            value="{{ optional($break->break_start_at)->format('H:i') ?? '' }}" readonly>
                    <span class="detail__text-tilde">〜</span>
                    <input class="detail__text--input-correction" type="text"
                            value="{{ optional($break->break_end_at)->format('H:i') ?? '' }}" readonly>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            @endforeach
            @endif
            <div class="detail__table-bottom">
                <dt class="detail__table-label">備考</dt>
                <dd class="detail__table-group text-left">
                    <textarea class="detail__textarea" name="reason" cols="20" rows="3" readonly>{{ $correction->reason ?? '' }}</textarea>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
        </dl>
        <div class="detail__button">
        @if ($correction->status === \App\Models\AttendanceCorrection::STATUS_PENDING)
            <form method="POST" action="{{ route('stamp_correction_request.approve.update', $correction) }}">
            @csrf
            @method('PUT')
            <button type="submit" class="detail__button--pending">承認</button>
            </form>
        @else
            <button type="button" class="detail__button--approved" disabled>承認済み</button>
        @endif
        </div>
    </div>
</div>
@endsection