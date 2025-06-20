@extends('layouts.luxury-nav')

@section('title', 'หน้ารายละเอียด')

@section('content')
<?php

use App\Models\Config;

$config = Config::first();
?>
<style>
    .title-buy {
        font-size: 30px;
        font-weight: bold;
        color: <?= $config->color_font != '' ? $config->color_font : '#ffffff' ?>;
    }

    .title-list-buy {
        font-size: 25px;
        font-weight: bold;
    }

    .btn-plus {
        background: none;
        border: none;
        color: rgb(0, 156, 0);
        cursor: pointer;
        padding: 0;
        font-size: 12px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .btn-plus:hover {
        color: rgb(185, 185, 185);
    }

    .btn-delete {
        background: none;
        border: none;
        color: rgb(192, 0, 0);
        cursor: pointer;
        padding: 0;
        font-size: 12px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.3s ease;
    }

    .btn-delete:hover {
        color: rgb(185, 185, 185);
    }

    .btn-aprove {
        background: linear-gradient(360deg, var(--primary-color), var(--sub-color));
        border-radius: 20px;
        border: 0px solid #0d9700;
        padding: 5px 0px;
        font-weight: bold;
        text-decoration: none;
        color: rgb(255, 255, 255);
        transition: background 0.3s ease;
    }

    .btn-aprove:hover {
        background: linear-gradient(360deg, var(--sub-color), var(--primary-color));
        cursor: pointer;
    }

    svg {
        width: 100%;
    }
</style>
<div class="container my-2">
    <div class="d-flex flex-column align-items-center">
        <div class="title-buy mb-1">
            🛒 รายการอาหารที่สั่ง
        </div>
        <div class="card w-100 shadow-sm">
            <div class="card-header bg-primary text-white">
                <strong>ออเดอร์ของคุณ</strong>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php $total = 0; ?>
                    @foreach($orderlist as $rs)
                    <div class="list-group-item list-group-item-action mb-2 p-4 rounded border bg-light">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start">
                            <div class="mb-3 mb-md-0">
                                <h5 class="mb-1 fw-bold">เลขออเดอร์ #{{$rs->id}}</h5>
                                <div class="mb-2 d-flex">
                                    <div class="me-2 fw-bold" style="min-width: 110px;">สถานะออเดอร์:</div>
                                    <div class="text-muted">
                                        <?php switch ($rs->status) {
                                            case 1:
                                                echo 'กำลังทำอาหาร';
                                                break;
                                            case 2:
                                                echo 'เสิร์ฟออเดอร์แล้ว';
                                                break;
                                            case 3:
                                                echo 'จัดส่งสำเร็จ';
                                                break;
                                            default:
                                                break;
                                        } ?>
                                    </div>
                                </div>
                            </div>
                            <?php $total = $total + $rs->total; ?>
                            <div class="text-end mt-3 mt-md-0">
                                <small class="text-muted">ราคา: {{ number_format($rs->total ?? 0, 2) }} บาท</small><br>
                                <button data-id="{{$rs->id}}" type="button" class="btn btn-sm btn-success modalShow">
                                    ดูรายละเอียดออเดอร์
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @if($orderlist->isEmpty())
                <div class="text-center text-muted my-2">
                    ไม่มีรายการอาหารที่สั่ง
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
<div class="container">
    <div class="d-flex flex-column justify-content-center gap-2">
        <div class="title-buy">
            ชำระเงิน
        </div>
        <div class="bg-white px-2 pt-3 shadow-lg d-flex flex-column aling-items-center justify-content-center" style="border-radius: 10px;">
            <div class="title-list-buy">
                ยอดชำระ
            </div>
            <div class="fw-bold text-center" style="font-size:45px; ">
                <span id="total-price" style="color: #0d9700"><?= number_format($total ?? 0, 2) ?></span><span class="text-dark ms-2">บาท</span>
            </div>
            <textarea class="form-control fw-bold text-center bg-white mb-2" style="border-radius: 10px;" rows="4"
                id="remark" placeholder="หมายเหตุ (ความต้องการเพิ่มเติม)"></textarea>
        </div>
    </div>
</div>
<div class="container my-4">
    <div class="d-flex flex-column align-items-center">
        <div class="card w-100">
            <div class="card-header bg-primary text-white">
                ข้อมูลชำระเงิน
            </div>
            <div class="card-body">
                @csrf
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <?= $qr_code ?>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-12">
                        <label for="silp" class="form-label d-flex justify-content-start">แนบสลิป : </label>
                        <input type="file" class="form-control" id="silp" name="silp" required accept="image/jpeg, image/png">
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button class="btn btn-sm btn-primary" id="confirm-order-btn" type="button">ยืนยันการชำระเงิน</button>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).on('click', '.modalShow', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        $.ajax({
            type: "post",
            url: "{{ route('listorderDetails') }}",
            data: {
                id: id
            },
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function(response) {
                $('#modal-detail').modal('show');
                $('#body-html').html(response);
            }
        });
    });
</script>
<div class="modal fade" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true" id="modal-detail">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">รายละเอียดออเดอร์</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="body-html">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const confirmButton = document.getElementById('confirm-order-btn');

        confirmButton.addEventListener('click', function(event) {
            event.preventDefault();

            const fileInput = document.getElementById('silp');
            const file = fileInput.files[0];

            if (!file) {
                Swal.fire("กรุณาแนบสลิปก่อน", "", "warning");
                return;
            }

            Swal.showLoading();
            const formData = new FormData();
            formData.append('remark', $('#remark').val());
            formData.append('silp', file);

            $.ajax({
                type: "POST",
                url: "{{ route('confirmPay') }}",
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.status == true) {
                        Swal.fire(response.message, "", "success");
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        Swal.fire(response.message, "", "error");
                    }
                },
                error: function(xhr) {
                    Swal.fire("เกิดข้อผิดพลาด", xhr.responseText, "error");
                }
            });
        });
    });
</script>
@endsection