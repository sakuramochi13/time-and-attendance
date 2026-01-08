@extends('layouts.app')

@section('css')
<link rel="stylesheet" href="{{ asset('css/admin/staff.css')}}">
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
        <h1 class="correction-request__title">スタッフ一覧</h1>
        <table class="correction-request__table">
            <thead class="correction-request__table-title">
                <tr>
                    <th class="correction-request__col--name">名前</th>
                    <th class="correction-request__col--email">メールアドレス</th>
                    <th class="correction-request__col--month">月次勤怠</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($staff as $user)
                    <tr class="correction-request__table-cell">
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <a class="correction-request__table-cell--detail"
                            href="{{ route('admin.attendance.staff.show', ['user' => $user->id]) }}">
                            詳細
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr class="correction-request__table-cell">
                        <td colspan="3">スタッフがいません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="pagination">
            {{ $staff->links() }}
        </div>
    </div>
</div>
@endsection