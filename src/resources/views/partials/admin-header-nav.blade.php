<div class="nav-group">
    <nav class="nav-item">
        <a href="{{ url('/admin/attendance/list') }}" class="nav-link">勤怠一覧</a>
        <a href="{{ url('/admin/staff/list') }}" class="nav-link">スタッフ一覧</a>
        <a href="{{ route('stamp_correction_request.list') }}" class="nav-link">申請一覧</a>
        <form class="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display:inline;">
            @csrf
            <button class="nav-link__logout" type="submit">ログアウト</button>
        </form>
    </nav>
</div>
