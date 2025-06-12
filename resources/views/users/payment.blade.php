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
        border-radius: 10px;
        border: 0px solid #0d9700;
        padding: 5px 5px;
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
<div class="container">
    <div class="d-flex flex-column justify-content-center gap-2">
        <div class="title-buy">
            ชำระเงิน
        </div>
        <div class="bg-white px-2 pt-3 shadow-lg d-flex flex-column aling-items-center justify-content-center"
            style="border-radius: 10px;">
            <div class="title-list-buy">
                รายการอาหารที่สั่ง
            </div>
            <div id="order-summary" class="mt-2"></div>
            <div class="fw-bold fs-5 mt-5 " style="border-top:2px solid #7e7e7e; margin-bottom:-10px;">
                ยอดชำระ
            </div>
            <div class="fw-bold text-center" style="font-size:45px; ">
                <span id="total-price" style="color: #0d9700"></span><span class="text-dark ms-2">บาท</span>
            </div>
        </div>
        <div class="bg-white p-2 shadow-lg mt-3" style="border-radius:10px;">
            <textarea class="form-control fw-bold text-center bg-white p-2" style="border-radius: 10px;" rows="4"
                id="remark" placeholder="หมายเหตุ(ความต้องการเพิ่มเติม)"></textarea>
        </div>
    </div>
</div>
<!-- <form action="" method="post" enctype="multipart/form-data"> -->
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
        </div>
        <button class="btn-aprove mt-3" style="display: none;" id="confirm-order-btn" type="button">ยืนยันการชำระเงิน</button>
    </div>
</div>
<!-- </form> -->
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let orderData = JSON.parse(localStorage.getItem('orderData')) || {};
        const container = document.getElementById('order-summary');
        const totalPriceEl = document.getElementById('total-price');

        function renderOrderList() {
            container.innerHTML = '';
            let total = 0;

            if (Object.keys(orderData).length === 0) {
                const noItemsMessage = document.createElement('div');
                noItemsMessage.textContent = "ท่านยังไม่ได้เลือกสินค้า";
                container.appendChild(noItemsMessage);
            } else {
                for (const name in orderData) {
                    for (const type in orderData[name]) {
                        const item = orderData[name][type];
                        if (item.qty > 0) {
                            const el = document.createElement('div');
                            el.classList.add('d-flex', 'justify-content-between', 'align-items-center', 'mb-1');

                            const itemText = document.createElement('div');
                            itemText.textContent = `${name} (${type}) x${item.qty}`;

                            const rightSide = document.createElement('div');
                            rightSide.classList.add('d-flex', 'align-items-center', 'gap-2');

                            const priceText = document.createElement('div');
                            priceText.textContent = `${item.qty * item.price} ฿`;

                            rightSide.appendChild(priceText);

                            el.appendChild(itemText);
                            el.appendChild(rightSide);
                            container.appendChild(el);

                            total += item.qty * item.price;
                        }
                    }
                }
            }

            totalPriceEl.textContent = `${total}`;
        }

        renderOrderList();
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const orderData = JSON.parse(localStorage.getItem('orderData')) || {};

        const confirmButton = document.getElementById('confirm-order-btn');

        if (Object.keys(orderData).length > 0) {
            confirmButton.style.display = 'inline-block';
        }

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
            formData.append('orderData', JSON.stringify(orderData)); // แปลงเป็น string ก่อนส่ง
            formData.append('remark', $('#remark').val());
            formData.append('silp', file); // แนบไฟล์

            $.ajax({
                type: "POST",
                url: "{{ route('SendOrder') }}",
                data: formData,
                processData: false, // สำคัญ: ปิดการแปลงข้อมูล
                contentType: false, // สำคัญ: ให้ browser จัดการ content-type เอง
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.status == true) {
                        Swal.fire(response.message, "", "success");
                        localStorage.removeItem('orderData');
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