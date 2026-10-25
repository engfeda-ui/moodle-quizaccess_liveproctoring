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
$string['setting:warninglimit'] = 'الحد الأقصى للتحذيرات';
$string['setting:autosubmit'] = 'التسليم التلقائي عند بلوغ الحد';
$string['setting:showwatermark'] = 'عرض العلامة المائية باسم الطالب';
$string['setting:blocktabs'] = 'التحذير عند تبديل النافذة';

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
