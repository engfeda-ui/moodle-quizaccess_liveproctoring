<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Arabic language strings for quizaccess_liveproctoring.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'المراقبة الحية بالذكاء الاصطناعي';
$string['plugindescription'] = 'يراقب الطلاب أثناء الامتحانات عبر لقطات من الكاميرا وتحليل ذكاء اصطناعي مخصص، مع لوحة تحكم حية للمراقب.';

// إعدادات الامتحان.
$string['setting:enablecamera'] = 'تفعيل مراقبة الكاميرا';
$string['setting:enableai'] = 'تفعيل تحليل الذكاء الاصطناعي';
$string['setting:snapshotinterval'] = 'الفترة بين اللقطات (بالثواني)';
$string['setting:resolution'] = 'دقة كاميرا الويب (العرض بالبكسل)';
$string['setting:referencerequired'] = 'إلزامية التحقق من الهوية (صورة مرجعية قبل الاختبار)';
$string['task:analyzesnapshots'] = 'تحليل لقطات المراقبة المعلقة بالذكاء الاصطناعي';
$string['setting:warninglimit'] = 'الحد الأقصى للتحذيرات';
$string['setting:autosubmit'] = 'التسليم التلقائي عند بلوغ الحد';
$string['setting:showwatermark'] = 'عرض العلامة المائية باسم الطالب';
$string['setting:blocktabs'] = 'التحذير عند تبديل النافذة';
$string['setting:requiremic'] = 'إلزامية تشغيل الميكروفون مع الكاميرا';
$string['setting:detectspeech'] = 'رصد التحدث والأصوات المرتفعة';
$string['setting:preventscreenshot'] = 'حظر ورصد لقطات الشاشة والطباعة ومسح الحافظة';
$string['setting:blockvirtualcamera'] = 'حظر الكاميرات الوهمية وبرامج البث (OBS / V4L2)';
$string['setting:blockmultiscreen'] = 'حظر استخدام شاشات عرض متعددة أو شاشات ممتدة';
$string['setting:reconnectgrace'] = 'مهلة استعادة الكاميرا بالثواني قبل الطرد التلقائي';

// إعدادات المشرف.
$string['setting:aiapiurl'] = 'عنوان URL لـ AI API';
$string['setting:aiapiurldesc'] = 'الرابط الكامل لـ API الذكاء الاصطناعي الخاص بك.';
$string['setting:aiapikey'] = 'مفتاح AI API';
$string['setting:aiapidesc'] = 'مفتاح المصادقة (Bearer Token) لـ API الذكاء الاصطناعي.';
$string['setting:aimodel'] = 'اسم نموذج الذكاء الاصطناعي';
$string['setting:aimodeldesc'] = 'معرف النموذج في حال استخدام واجهات OpenAI / OpenRouter (مثل openrouter/free أو google/gemma-4-31b-it:free أو gpt-4o-mini).';
$string['setting:aitimeout'] = 'مهلة انتظار AI API (بالثواني)';
$string['setting:aitimeoutdesc'] = 'المدة القصوى للانتظار قبل اعتبار الطلب فاشلاً.';
$string['setting:globalheading'] = 'إعدادات AI API العامة';

// رسائل الطالب.
$string['studentnotice'] = '📷 هذا الامتحان يستخدم المراقبة الحية. ستكون الكاميرا نشطة طوال مدة الامتحان. تأكد من الإضاءة الجيدة وإبقاء وجهك ظاهراً.';
$string['warningtitle'] = '⚠️ تحذير مراقبة';
$string['warningmessage'] = 'تم رصد مخالفة في نظام المراقبة.';
$string['warningcount'] = 'تحذير';
$string['camerablocked'] = 'تم رفض الوصول إلى الكاميرا. يرجى السماح بالوصول ثم إعادة تحميل الصفحة.';
$string['tabswitchwarning'] = 'غادرت نافذة الامتحان.';
$string['autosubmitmessage'] = 'تجاوزت الحد الأقصى للمخالفات. تم تسليم امتحانك تلقائياً.';
$string['cameragate_title'] = 'المراقبة بالكاميرا إلزامية لدخول هذا الاختبار';
$string['cameragate_desc'] = 'هذا الاختبار خاضع للمراقبة المباشرة. الأسئلة ستبقى محجوبة بالكامل حتى يتم تفعيل الكاميرا والموافقة عليها.';
$string['cameragate_btn'] = '📷 تفعيل الكاميرا والموافقة للدخول';
$string['cameragate_retry'] = '🔄 إعادة المحاولة والسماح بالكاميرا';
$string['cameragate_denied'] = 'تم رفض الوصول إلى الكاميرا! لا يمكن دخول الامتحان دون السماح للكاميرا من إعدادات المتصفح.';
$string['cameragate_lost'] = 'انقطعت الكاميرا! يرجى إعادة تشغيل الكاميرا فوراً قبل إخراجك من الامتحان وتسليمه تلقائياً.';
$string['ejectedmessage'] = 'تم إنهاء وإغلاق محاولة الامتحان الخاصة بك من قِبل مراقب الامتحان بسبب مخالفة قواعد المراقبة. لا يمكنك الوصول لهذا الامتحان مجدداً.';
$string['warninglimitexceeded'] = 'لقد تجاوزت الحد الأقصى المسموح به من مخالفات المراقبة. تم تسليم وقفل الامتحان.';
$string['virtualcamerablocked'] = 'تم اكتشاف كاميرا وهمية ({$a}). يُمنع منعاً باتاً استخدام الكاميرات الافتراضية وبرامج البث أثناء الامتحان. يرجى اختيار كاميرا الويب الحقيقية للمتابعة.';
$string['multimonitorwarning'] = 'تم اكتشاف شاشات عرض متعددة متصلة بجهازك. يُمنع استخدام شاشات إضافية أثناء الامتحان لضمان النزاهة.';

// لوحة المراقب.
$string['livemonitor'] = 'المراقبة الحية';
$string['viewreport'] = 'تقرير ما بعد الامتحان';
$string['activestudents'] = 'الطلاب النشطون';
$string['suspicious'] = 'مشبوه';
$string['lastupdate'] = 'آخر تحديث';
$string['connecting'] = 'جارٍ الاتصال بالبث الحي…';
$string['riskhigh'] = 'خطر مرتفع';
$string['riskmedium'] = 'تحذير';
$string['riskok'] = 'طبيعي';
$string['facenotdetected'] = 'لا يوجد وجه';
$string['multiplefaces'] = 'وجوه متعددة';
$string['lastseen'] = 'آخر ظهور';
$string['nostudents'] = 'بانتظار بدء الطلاب للاختبار...';
$string['nostudentsdesc'] = 'ستظهر كاميرات وحالات الطلاب هنا تلقائياً فور بدء محاولاتهم.';

// قاعة الامتحان المرئية (زووم).
$string['examhall'] = 'قاعة المراقبة المرئية';
$string['spotlight'] = '🎥 مراقبة حية ومباشرة';
$string['spotlight_title'] = '🔴 بث فيديو حي ومباشر';
$string['connecting_live'] = '📡 جارٍ الاتصال المباشر بكاميرا الطالب...';
$string['live_active'] = '🟢 متصل ومباشر (30 FPS)';
$string['send_warning'] = '📢 إرسال إنذار فوري';
$string['warning_placeholder'] = 'اكتب رسالة تنبيه تظهر للطالب فوراً...';
$string['terminate_btn'] = '🚪 طرد الطالب وإنهاء اختباره';
$string['terminate_prompt'] = 'هل أنت متأكد من رغبتك في سحب ورقة الاختبار وطرد هذا الطالب فوراً؟';
$string['filter_all'] = 'جميع الطلاب';
$string['filter_flagged'] = 'المخالفون فقط';
$string['sound_toggle'] = 'أصوات التنبيه';
$string['fullscreen'] = 'ملء الشاشة';
$string['warning_sent'] = 'تم إرسال الإنذار للطالب بنجاح!';
$string['studentaudio'] = 'صوت الطالب';
$string['audiomuted'] = 'الصوت: مكتوم';
$string['audiounmuted'] = 'الصوت: مفعّل';
$string['classroom'] = 'الفصل / المجموعة';
$string['record_clip'] = 'تسجيل مقطع إثبات';
$string['recording_clip'] = 'جارٍ تسجيل الإثبات';
$string['stop_record_clip'] = 'إيقاف وحفظ الإثبات';
$string['clip_saved'] = 'تم حفظ وتوثيق مقطع الفيديو كدليل رسمي في تقرير الامتحان بنجاح!';
$string['evidence_videos'] = 'مقاطع الفيديو المسجلة كدليل إثبات';
$string['no_evidence_videos'] = 'لا توجد مقاطع فيديو مسجلة لهذا الطالب.';
$string['breach_events'] = 'سجل الخروقات والمخالفات الأمنية';
$string['screenshot_detected'] = 'محاولة التقاط صورة للشاشة (Screenshot)';
$string['speaking_detected'] = 'رصد تحدث أو أصوات مرتفعة أثناء الاختبار';
$string['face_disappeared'] = 'اختفاء وجه الطالب من إطار الكاميرا';
$string['cameramic_required'] = 'الكاميرا والميكروفون إلزاميان تماماً لدخول هذا الامتحان، ولا يُقبل البدء دونهما.';

// التحقق المسبق من الكاميرا والمايك قبل بدء الامتحان.
$string['precheck_title'] = 'فحص ومطابقة الكاميرا والميكروفون قبل بدء الاختبار';
$string['precheck_desc'] = 'هذا الاختبار يخضع للمراقبة المرئية الحية والذكاء الاصطناعي. زر (بدء المحاولة الآن) مقفل ولن يتم تفعيله إلا بعد السماح بالكاميرا والمايك أدناه والتحقق من جهازك.';
$string['precheck_badge'] = 'خاضع للمراقبة الحية والذكاء الاصطناعي';
$string['precheck_placeholder'] = 'اضغط على الزر أدناه لتفعيل الكاميرا والمايك والتأكد من وضوح صورتك ومطابقة مظهرك.';
$string['precheck_cam_status'] = 'كاميرا الويب';
$string['precheck_mic_status'] = 'الميكروفون';
$string['precheck_screen_status'] = 'شاشة العرض';
$string['precheck_waiting'] = 'في انتظار الصلاحيات';
$string['precheck_checking'] = 'جارٍ فحص الجهاز...';
$string['precheck_cam_ok'] = 'متصلة ومطابقة';
$string['precheck_mic_ok'] = 'متصل ونشط';
$string['precheck_screen_ok'] = 'شاشة واحدة معتمدة';
$string['precheck_btn_verify'] = 'السماح بالكاميرا والمايك لتفعيل زر بدء الاختبار';
$string['precheck_btn_verifying'] = 'جارٍ الاتصال والتحقق من الجهاز...';
$string['precheck_btn_verified'] = 'تم التحقق من الكاميرا والجهاز بنجاح';
$string['precheck_success'] = 'تم التحقق من الكاميرا والميكروفون بنجاح! تم تفعيل زر (بدء المحاولة الآن) أدناه. بالتوفيق في اختبارك!';
$string['button_locked_hint'] = 'يجب تفعيل الكاميرا والميكروفون في صندوق التحقق بالأعلى أولاً لتفعيل هذا الزر.';
$string['must_verify_first'] = 'يرجى إكمال فحص الكاميرا والمايك والموافقة عليها بالأعلى أولاً قبل بدء المحاولة!';

// الصلاحيات.
$string['liveproctoring:viewmonitor'] = 'عرض شاشة المراقبة الحية';
$string['liveproctoring:viewsnapshots'] = 'عرض لقطات صور الامتحان';
$string['liveproctoring:deletesnapshots'] = 'حذف لقطات صور الامتحان';
$string['liveproctoring:manage'] = 'إدارة إعدادات المراقبة الحية';

// الخصوصية.
$string['privacy:metadata:quizaccess_liveproctoring_snaps'] = 'تخزين لقطات الكاميرا ودرجات الخطر الصادرة عن الذكاء الاصطناعي أثناء محاولات الاختبار.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:userid'] = 'معرف الطالب الذي تتم مراقبته.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:quizid'] = 'معرف الاختبار المراقب.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:attemptid'] = 'معرف محاولة الاختبار.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:risk_score'] = 'درجة الخطر المحسوبة عبر الذكاء الاصطناعي.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:face_detected'] = 'ما إذا تم اكتشاف وجه في الصورة.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:suspicious'] = 'ما إذا تم رصد سلوك مشبوه.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:timecreated'] = 'وقت التقاط الصورة.';
$string['privacy:metadata:quizaccess_liveproctoring_events'] = 'تخزين سجل المخالفات وأحداث المراقبة أثناء الامتحان.';
$string['privacy:metadata:quizaccess_liveproctoring_events:userid'] = 'معرف المستخدم الذي تسبب في الحدث.';
$string['privacy:metadata:quizaccess_liveproctoring_events:attemptid'] = 'معرف المحاولة التي وقع فيها الحدث.';
$string['privacy:metadata:quizaccess_liveproctoring_events:eventtype'] = 'نوع حدث المراقبة.';
$string['privacy:metadata:quizaccess_liveproctoring_events:severity'] = 'مستوى خطورة الحدث.';
$string['privacy:metadata:quizaccess_liveproctoring_events:timecreated'] = 'وقت وقوع الحدث.';
$string['privacy:metadata:core_files'] = 'ملفات صور الكاميرا المحفوظة في نظام المراقبة الحية.';
