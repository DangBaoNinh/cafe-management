@props(['options' => []])
@php($configuration = array_merge(['mode' => 'live'], $options))
<section class="seating-experience container" data-public-seating data-seating-config="{{ json_encode($configuration, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
<div class="seating-head">
<div class="seating-summary">
<span class="seat-availability-number" data-seat-available>—</span>
<div>
<strong>chỗ đang sẵn sàng</strong>
<span>trong <span data-seat-total>—</span> bàn và phòng của quán</span>
</div>
</div>
<div class="seating-data-label">
<span data-seat-mode>ĐANG KẾT NỐI</span>
<p data-seat-status role="status">Đang mở không gian bàn…</p>
</div>
<button class="btn secondary small" type="button" data-seat-refresh>
<x-theme.icon name="clock" />Cập nhật</button>
</div>
<div class="category-row" data-seat-areas aria-label="Chọn khu vực">
</div>
<div class="seating-layout">
<div class="seat-world">
<div class="seat-scene-heading">
<span>Không gian bàn 3D</span>
<button class="icon-btn" type="button" data-seat-reset aria-label="Trở về góc nhìn ban đầu">
<x-theme.icon name="home" />
</button>
</div>
<div class="seating-viewport" data-seat-viewport>
</div>
<div class="seat-pages" data-seat-pages aria-label="Chọn nhóm bàn trong 3D">
</div>
<div class="seat-scene-footer">
<span>Kéo để xoay · Cuộn để thu phóng</span>
<span>Chạm mã bàn để xem</span>
</div>
<div class="seat-legend">
<span>
<i class="available">
</i>Còn chỗ</span>
<span>
<i class="occupied">
</i>Có khách</span>
<span>
<i class="reserved">
</i>Đã giữ chỗ</span>
<span>
<i class="cleaning">
</i>Đang chuẩn bị</span>
</div>
</div>
<aside class="seat-sidebar">
<div class="seat-sidebar-head">
<h2>Góc nào dành cho bạn?</h2>
<label class="field">Đi cùng mấy người?<select data-seat-capacity>
<option value="1">Mọi sức chứa</option>
<option value="2">Từ 2 chỗ</option>
<option value="4">Từ 4 chỗ</option>
<option value="6">Từ 6 chỗ</option>
</select>
</label>
</div>
<div class="seat-list" data-seat-list>
</div>
<div class="seat-detail" data-seat-detail>
<h3>Chọn bàn để xem chi tiết.</h3>
</div>
</aside>
</div>
<p class="seat-privacy-note">Nhân vật là mô hình 3D minh họa mức sử dụng bàn, không phải hình ảnh khách thực tế. Tình trạng hiện tại giúp bạn tham khảo; chỗ ngồi sẽ được xác nhận theo thời gian yêu cầu.</p>
</section>
