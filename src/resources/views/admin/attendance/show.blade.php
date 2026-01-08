@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/attendance.css')}}">
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
        <form
            action="{{ $attendance->exists
                ? route('admin.attendance.update', $attendance->id)
                : route('admin.attendance.store')}}"
            method="POST"
            class="attendance__form"
        >
        @csrf
        @if ($attendance->exists)
            @method('PUT')
        @endif
        @unless ($attendance->exists)
            <input type="hidden" name="user_id" value="{{ $attendance->user_id }}">
            <input type="hidden" name="work_date" value="{{ optional($attendance->work_date)->toDateString() ?? $attendance->work_date }}">
        @endunless
            <div class="detail__table-middle">
                <dt class="detail__table-label">出勤・退勤</dt>
                <dd class="detail__table-group">
                    <input class="detail__text--input" type="text" name="clock_in_at"
                        value="{{ old('clock_in_at', optional($attendance->clock_in_at)->format('H:i')) }}"
                        {{ $isLocked ? 'disabled' : '' }}>
                    <span class="detail__text-tilde">〜</span>
                    <input class="detail__text--input" type="text" name="clock_out_at"
                        value="{{ old('clock_out_at', optional($attendance->clock_out_at)->format('H:i')) }}"
                        {{ $isLocked ? 'disabled' : '' }}>
                </dd>
                <dd class="detail__text-message">
                    @error('clock_in_at') {{ $message }} @enderror
                    @error('clock_out_at') {{ $message }} @enderror
                </dd>
            </div>
            <div id="breaks-fixed">
                @foreach ($breakRows as $row)
                @php $breakIndex = $row['index'];
                @endphp
                <div class="detail__table-middle break-row"
                    id="break-row-{{ $breakIndex }}"
                    data-index="{{ $breakIndex }}">
                    <dt class="detail__table-label">{{ $row['label'] }}</dt>
                    <dd class="detail__table-group">
                        <input type="hidden" name="breaks[{{ $breakIndex }}][id]" value="{{ $row['id'] }}">
                        <input class="detail__text--input" type="text"
                            name="breaks[{{ $breakIndex }}][break_start_at]"
                            value="{{ $row['start'] }}"
                            {{ $isLocked ? 'disabled' : '' }}>
                        <span class="detail__text-tilde">〜</span>
                        <input class="detail__text--input" type="text"
                            name="breaks[{{ $breakIndex }}][break_end_at]"
                            value="{{ $row['end'] }}"
                            {{ $isLocked ? 'disabled' : '' }}>
                    </dd>
                    <dd class="detail__text-message">
                        @error("breaks.$breakIndex.break_start_at") {{ $message }} @enderror
                        @error("breaks.$breakIndex.break_end_at") {{ $message }} @enderror
                    </dd>
                </div>
                @endforeach
            </div>
            <div class="detail__table-bottom">
                <dt class="detail__table-label">備考</dt>
                <dd class="detail__table-group">
                    <textarea class="detail__textarea" name="reason" cols="20" rows="3"
                        {{ $isLocked ? 'disabled' : '' }}>{{ old('reason') }}</textarea>
                </dd>
                <dd class="detail__text-message">
                    @error('reason') {{ $message }} @enderror
                </dd>
            </div>
        </dl>
            <div class="detail__button">
                @if($isLocked)
                <p class="detail__requesting">
                    <span class="asterisk-red">*</span>承認待ちのため修正はできません。
                </p>
                @endif
                @if (session('success'))
                <p class="flash-success">
                    <span class="asterisk-blue">*</span>{{ session('success') }}
                </p>
                @endif
                <button type="submit" class="update" {{ $isLocked ? 'disabled' : '' }}>更新</button>
            </div>
        </form>
    </div>
</div>
@endsection
