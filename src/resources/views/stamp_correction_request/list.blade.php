@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/stamp_correction_request.css')}}">
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
    <div class="correction-request__container">
        <h1 class="correction-request__title">申請一覧</h1>
        <div class="correction-request__status">
            <a href="{{ route('stamp_correction_request.list', ['status' => 'pending']) }}"
            class="correction-request__status-text {{ $status === 'pending' ? 'is-active' : '' }}">
                承認待ち
            </a>
            <a href="{{ route('stamp_correction_request.list', ['status' => 'approved']) }}"
            class="correction-request__status-text {{ $status === 'approved' ? 'is-active' : '' }}">
                承認済み
            </a>
        </div>
        <table class="correction-request__table">
            <thead class="correction-request__table-title">
                <tr>
                    <th class="correction-request__col--status">状態</th>
                    <th class="correction-request__col--name">名前</th>
                    <th class="correction-request__col--date">対象日時</th>
                    <th class="correction-request__col--reason">申請理由</th>
                    <th class="correction-request__col--request-at">申請日時</th>
                    <th class="correction-request__col--detail">詳細</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requests as $request)
                <tr class="correction-request__table-cell">
                    <td>{{ $request->status_label }}</td>
                    <td>{{ $request->user_name }}</td>
                    <td>{{ $request->work_date_ymd }}</td>
                    <td>{{ $request->reason_text }}</td>
                    <td>{{ $request->requested_at_ymd }}</td>
                    <td>
                        <a class="correction-request__table-cell--detail"
                            href="{{ $request->detail_url }}">
                            詳細
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

