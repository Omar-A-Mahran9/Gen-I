<?php

return [
    'messages' => [
        'retrieved' => 'تم جلب البيانات بنجاح.',
        'created' => 'تم الإنشاء بنجاح.',
        'updated' => 'تم التحديث بنجاح.',
        'deleted' => 'تم الحذف بنجاح.',
        'status_changed' => 'تم تغيير الحالة بنجاح.',
        'approved' => 'تم الاعتماد بنجاح.',
        'retired' => 'تم الإيقاف بنجاح.',
        'stock_adjusted' => 'تم تحديث المخزون بنجاح.',
        'idempotent_replay' => 'تمت معالجة هذا الطلب مسبقًا؛ تمت إعادة النتيجة الأصلية.',
        'token_issued' => 'تم إصدار رمز الدخول بنجاح.',
        'token_revoked' => 'تم إلغاء رمز الدخول بنجاح.',
    ],
    'errors' => [
        'bad_request' => 'صيغة الطلب غير صحيحة.',
        'validation_failed' => 'البيانات المدخلة غير صالحة.',
        'unauthenticated' => 'يجب تسجيل الدخول.',
        'forbidden' => 'غير مسموح لك بتنفيذ هذا الإجراء.',
        'not_found' => 'المورد المطلوب غير موجود.',
        'method_not_allowed' => 'طريقة HTTP هذه غير مدعومة لهذا المسار.',
        'conflict' => 'يتعارض الطلب مع الحالة الحالية للمورد.',
        'payload_too_large' => 'حجم الطلب أكبر من المسموح.',
        'unsupported_media_type' => 'نوع محتوى الطلب غير مدعوم.',
        'rate_limited' => 'طلبات كثيرة. يرجى التمهل.',
        'service_unavailable' => 'الخدمة غير متاحة مؤقتًا.',
        'server_error' => 'حدث خطأ غير متوقع.',
    ],
    'listing' => [
        'filter_format' => 'يجب إرسال عوامل التصفية بالصيغة filter[field]=value.',
        'unknown_filter' => 'عامل تصفية غير مدعوم: :keys. العوامل المسموح بها: :allowed.',
        'invalid_sort' => 'حقل الترتيب غير مدعوم. الحقول المسموح بها: :allowed (أضف "-" قبل الحقل للترتيب التنازلي).',
        'invalid_per_page' => 'يجب أن تكون قيمة per_page عددًا صحيحًا بين 1 و:max.',
        'invalid_page' => 'يجب أن تكون قيمة page عددًا صحيحًا لا يقل عن 1.',
    ],
];
