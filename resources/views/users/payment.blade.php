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

<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            formData.append('orderData', JSON.stringify(orderData));
            formData.append('remark', $('#remark').val());
            formData.append('silp', file);

            $.ajax({
                type: "POST",
                url: "{{ route('SendOrder') }}",
                data: formData,
                processData: false,
                contentType: false,
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