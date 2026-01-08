@extends('layouts/app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/user/detail.css')}}">
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

        {{-- A: 編集可（ただし承認待ちがあると新規申請は不可） --}}
        @if(!$isReadonly && $canRequestNew)
        <form action="{{ route('attendance.detail.store', $attendance->id) }}" method="POST" class="attendance-detail__form">
            @csrf
            <dl class="detail__table">
            <div class="detail__table-top">
                <dt class="detail__table-label">名前</dt>
                <dd class="detail__table-group">
                    <span class="detail__text-name">{{ $attendance->user->name }}</span>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            <div class="detail__table-middle">
                <dt class="detail__table-label">日付</dt>
                <dd class="detail__table-group">
                    <span class="detail__text">{{ $attendance->work_date->format('Y年') }}</span>
                    <span class="detail__text-tilde">　</span>
                    <span class="detail__text">{{ $attendance->work_date->format('n月j日') }}</span>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            <div class="detail__table-middle">
                <dt class="detail__table-label">出勤・退勤</dt>
                <dd class="detail__table-group">
                    <input class="detail__text--input" type="text" name="clock_in_at"
                        value="{{ old('clock_in_at', optional($attendance->clock_in_at)->format('H:i')) }}">
                    <span class="detail__text-tilde">〜</span>
                    <input class="detail__text--input" type="text" name="clock_out_at"
                        value="{{ old('clock_out_at', optional($attendance->clock_out_at)->format('H:i')) }}">
                </dd>
                <dd class="detail__text-message">
                @error('clock_in_at') {{ $message }} @enderror
                @error('clock_out_at') {{ $message }} @enderror
                </dd>
            </div>
            <div id="breaks-fixed">
                @foreach($breakRows as $row)
                <div class="detail__table-middle break-row" data-index="{{ $row['index'] }}">
                    <dt class="detail__table-label">{{ $row['label'] }}</dt>
                    <dd class="detail__table-group">
                        <input class="detail__text--input"
                            type="text"
                            name="breaks[{{ $row['index'] }}][start]"
                            value="{{ $row['start'] }}">

                        <span class="detail__text-tilde">〜</span>

                        <input class="detail__text--input"
                            type="text"
                            name="breaks[{{ $row['index'] }}][end]"
                            value="{{ $row['end'] }}">
                    </dd>
                    <dd class="detail__text-message">
                    @error("breaks.{$row['index']}.start") {{ $message }} @enderror
                    @error("breaks.{$row['index']}.end") {{ $message }} @enderror
                </dd>
                </div>
                @endforeach
            </div>
            <div class="detail__table-bottom">
                <dt class="detail__table-label">備考</dt>
                <dd class="detail__table-group">
                    <textarea class="detail__textarea" name="reason" cols="20" rows="3">
                        {{ old('reason') }}
                    </textarea>
                </dd>
                <dd class="detail__text-message">
                @error('reason') {{ $message }} @enderror
                </dd>
            </div>
            </dl>

            <div class="detail__button">
            <button type="submit" class="detail__button--update">修正</button>
            </div>
        </form>

        {{-- Aだけど承認待ちがあるので新規修正不可（表示はBと同じ文言） --}}
        @elseif(!$isReadonly && !$canRequestNew)
        <dl class="detail__table">
            {{-- 名前 --}}
            <div class="detail__table-top">
                <dt class="detail__table-label">名前</dt>
                <dd class="detail__table-group">
                    <span class="detail__text-name">{{ $attendance->user->name }}</span>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            <div class="detail__table-middle">
                <dt class="detail__table-label">日付</dt>
                <dd class="detail__table-group">
                    <span class="detail__text">{{ $attendance->work_date->format('Y年') }}</span>
                    <span class="detail__text-tilde">　</span>
                    <span class="detail__text">{{ $attendance->work_date->format('n月j日') }}</span>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            <div class="detail__table-middle">
                <dt class="detail__table-label">出勤・退勤</dt>
                <dd class="detail__table-group">
                    <input class="detail__text--input-correction" type="text"
                    value="{{ optional($attendance->clock_in_at)->format('H:i') }}" disabled>
                    <span class="detail__text-tilde">〜</span>
                    <input class="detail__text--input-correction" type="text"
                    value="{{ optional($attendance->clock_out_at)->format('H:i') }}" disabled>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            @if(($attendance->breaks ?? collect())->isEmpty())
            <div class="detail__table-middle">
                <dt class="detail__table-label">休憩</dt>
                <dd class="detail__table-group">
                <input class="detail__text--input-correction" type="text" value="" disabled>
                <span class="detail__text-tilde">〜</span>
                <input class="detail__text--input-correction" type="text" value="" disabled>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            @else
            @foreach(($attendance->breaks ?? collect()) as $breakIndex => $break)
                <div class="detail__table-middle">
                    <dt class="detail__table-label">
                        {{ $breakIndex === 0 ? '休憩' : '休憩' . ($breakIndex + 1) }}
                    </dt>
                    <dd class="detail__table-group">
                        <input class="detail__text--input-correction" type="text"
                        value="{{ optional($break->break_start_at)->format('H:i') }}" disabled>
                        <span class="detail__text-tilde">〜</span>
                        <input class="detail__text--input-correction" type="text"
                        value="{{ optional($break->break_end_at)->format('H:i') }}" disabled>
                    </dd>
                    <dd class="detail__text-message"></dd>
                </div>
            @endforeach
            @endif
            <div class="detail__table-bottom">
                <dt class="detail__table-label">備考</dt>
                <dd class="detail__table-group">
                    <p class="detail__textarea-correction"></p>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
        </dl>
            <div class="detail__notice">
                <p class="detail__requesting">
                    <span class="asterisk">*</span>承認待ちのため修正はできません。
                </p>
            </div>

        {{-- B: 読み取り専用（承認待ち/承認済） --}}
        @else
        <dl class="detail__table">
            <div class="detail__table-top">
                <dt class="detail__table-label">名前</dt>
                <dd class="detail__table-group">
                    <span class="detail__text-name">{{ $attendance->user->name }}</span>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            <div class="detail__table-middle">
                <dt class="detail__table-label">日付</dt>
                <dd class="detail__table-group">
                    <span class="detail__text">{{ $attendance->work_date->format('Y年') }}</span>
                    <span class="detail__text-tilde">　</span>
                    <span class="detail__text">{{ $attendance->work_date->format('n月j日') }}</span>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            <div class="detail__table-middle">
                <dt class="detail__table-label">出勤・退勤</dt>
                <dd class="detail__table-group">
                    <input class="detail__text--input-correction" type="text"
                    value="{{ optional($correction->requested_clock_in_at)->format('H:i') }}" disabled>
                    <span class="detail__text-tilde">〜</span>
                    <input class="detail__text--input-correction" type="text"
                    value="{{ optional($correction->requested_clock_out_at)->format('H:i') }}" disabled>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            @if($correctionBreaks->isEmpty())
            <div class="detail__table-middle">
                <dt class="detail__table-label">休憩</dt>
                <dd class="detail__table-group">
                    <input class="detail__text--input-correction" type="text" value="" disabled>
                    <span class="detail__text-tilde">〜</span>
                    <input class="detail__text--input-correction" type="text" value="" disabled>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
            @else
            @foreach($correctionBreaks as $breakIndex => $break)
                <div class="detail__table-middle">
                    <dt class="detail__table-label">
                        {{ $breakIndex === 0 ? '休憩' : '休憩' . ($breakIndex + 1) }}
                    </dt>
                    <dd class="detail__table-group">
                        <input class="detail__text--input-correction" type="text"
                        value="{{ optional($break->break_start_at)->format('H:i') }}" disabled>
                        <span class="detail__text-tilde">〜</span>
                        <input class="detail__text--input-correction" type="text"
                        value="{{ optional($break->break_end_at)->format('H:i') }}" disabled>
                    </dd>
                <dd class="detail__text-message"></dd>
                </div>
            @endforeach
            @endif
            <div class="detail__table-bottom">
                <dt class="detail__table-label">備考</dt>
                <dd class="detail__table-group">
                    <p class="detail__textarea-correction">{{ $correction->reason }}</p>
                </dd>
                <dd class="detail__text-message"></dd>
            </div>
        </dl>
        <div class="detail__notice">
            @if($correction->status === \App\Models\AttendanceCorrection::STATUS_PENDING)
            <p class="detail__requesting">
                <span class="asterisk">*</span>承認待ちのため修正はできません。
            </p>
            @elseif($correction->status === \App\Models\AttendanceCorrection::STATUS_APPROVED)
            <p class="detail__requesting--done">
                <span class="asterisk--done">*</span>承認済のため修正はできません。
            </p>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection



