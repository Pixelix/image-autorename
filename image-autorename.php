<?php

/*
* Plugin Name: Image Autorename
* Plugin URI: http://pixelix.ru/image-autorename/
* Description: Automatically complete fields for files attached to the posts.
* Version: 2.1
* Author: Pixelix
* Author URI: http://pixelix.ru
* License: GPL2
* Text Domain: image-autorename
* Domain Path: /lang/
*/

// Подключаем стили
function add_iar_style(){
	// Файл CSS, лежащий в папке с плагином
	wp_enqueue_style( 'image-autorename', plugins_url('image-autorename.css', __FILE__) ) ;
}
// Привязка к инициализации админки (УТОЧНИТЬ К ЧЕМУ)
add_action( 'admin_init', 'add_iar_style' );

// Подключаем перевод
function iar_init() {
	// Файлы лежат в подпапке lang
	load_plugin_textdomain( 'image-autorename', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
}
// Привязка к инициализации сайта (УТОЧНИТЬ, МОЖЕТ ПОМЕНЯТЬ)
add_action('init', 'iar_init');

// Замена содержимого полей
function image_autorename($post_id) {
	// Берём заголовок записи-родителя (та, к которой сработал хук)
	$the_title = get_the_title($post_id);
	// Тип поста attachment, выбираем только прикреплённые к записи
	$get_att = array(
		'post_type' => 'attachment',
		'post_parent' => $post_id,
		'posts_per_page' => '-1',
	);
	// Получаем объекты файлов в массив
	$attachments = get_posts( $get_att );
	// Для каждого файла
	foreach ( $attachments as $attachment ) {
		// Получаем текущий статус
		$iar_status = get_post_meta( $attachment->ID, 'iar_status', true );
		// Если статус не отрицательный (может быть положительным и пустым)
		if ($iar_status != 'no'){
			// Создаём массив
			$iar_update = array();
			// Берём ID файла
			$iar_update['ID'] = $attachment->ID;
			// В описание - название записи
			$iar_update['post_content'] = $the_title;
			// В заголовок - название записи
			$iar_update['post_title'] = $the_title;
			// В подпись - название записи
			$iar_update['post_excerpt'] = $the_title;
			// Обновляем файл с новыми полями
			wp_update_post( $iar_update );
			// В поле атрибута alt - название записи
			update_metadata('post', $attachment->ID, '_wp_attachment_image_alt', $the_title);
		}
	}
}
// Привязка к сохранению записи любого типа
add_action( 'save_post', 'image_autorename' );

// Создаём колонку в библиотеке файлов
function add_iar_column( $columns ) {
	// Задаём название колонки
	$columns[ 'iar_status' ] = __('Autorename', 'image-autorename');
	return $columns;
}
// Фильтр колонок для страницы upload внутри админки (УЗНАТЬ, ПОЧЕМУ 2 ПАРАМЕТРА)
add_filter( 'manage_upload_columns', 'add_iar_column', 10, 2 );

// Заполняем колонку в библиотеке файлов
function fill_iar_column( $column_name, $post_id ) {
	// Перебираем колонки
	switch( $column_name ) {
		// Если это та колонка, которую раньше создали
		case 'iar_status':
			// Получаем статус файла
			$iar_status = get_post_meta( $post_id, 'iar_status', true );
			// Если статус пустой, то считаем, что yes
			if (!$iar_status){
				$iar_status = 'yes';
			}
			// Выводим сразу все кнопки (для обоих статусов)
			echo '<span class="iar-'.$iar_status.'" id="iar-id-'.$post_id.'">';
			echo '<span class="no">';
			_e('Off', 'image-autorename');
			echo '&nbsp;&mdash; ';
			echo '<a class="iar-switch" href="admin.php?action=switch_iar_status&post=' . $post_id . '" iar-id="'.$post_id.'">';
			_e('Turn on', 'image-autorename');
			echo '</a>';
			echo '</span><span class="yes">';
			_e('On', 'image-autorename');
			echo '&nbsp;&mdash; ';
			echo '<a class="iar-switch" href="admin.php?action=switch_iar_status&post=' . $post_id . '" iar-id="'.$post_id.'">';
			_e('Turn off', 'image-autorename');
			echo '</a></span></span>';
		break;
	}
}
// Привязка (УЗНАТЬ, К ЧЕМУ)
add_action( 'manage_media_custom_column', 'fill_iar_column', 10, 2 );

// Создаём новое поле для режима редактирования файла библиотеки
function add_iar_status_field( $form_fields, $post ) {
	// Получаем текущий статус
    $field_value = get_post_meta( $post->ID, 'iar_status', true );
	// Если статус no, переменная принимает значение
	if($field_value == 'no'){
		$select = 'selected="selected"';
	}
	// Указываем название поля и массив параметров
    $form_fields['iar_status'] = array(
		// Текст для лэйбла
        'label' => __('Autorename', 'image-autorename'),
		// Текст для подсказки
        'helps' => __('Are fields will be complete after parent post will be update?', 'image-autorename'),
		// Формат - html, чтобы можно было сделать список со своим форматированием
		'input' => 'html',
		// Задаём структуру поля
		'html' => '
			<select name="iar_status_field" id="iar_status_field">
				<option value="yes">'.__('On', 'image-autorename').'</option>
				<option value="no" '.$select.'>'.__('Off', 'image-autorename').'</option>
			</select>
		',
    );
    return $form_fields;
}
// Фильтр полей на экране редактирования файла
add_filter( 'attachment_fields_to_edit', 'add_iar_status_field', 10, 2 );

// Сохраняем значение поля на экране редактирования файла библиотеки (УТОЧНИТЬ, ТАК ЛИ ЭТО)
function save_iar_status_field( $attachment_id ) {
	// Если поле не пустое (ОНО НЕ МОЖЕТ БЫТЬ ПУСТЫМ, ПОДУМАТЬ)
    if ( isset( $_REQUEST['iar_status_field'] ) ) {
		// Значение поля - в переменную
        $iar_status = $_REQUEST['iar_status_field'];
		// Если значение no
		if ($iar_status == 'no'){
			// Ставим статус no
			update_post_meta( $attachment_id, 'iar_status', 'no' );
		}
		// В остальных случаях (ОНИ БУДУТ?)
		else {
			// Ставим статус yes
			update_post_meta( $attachment_id, 'iar_status', 'yes' );
		}
    }
}
// (ЧТО ЭТО?)
add_action( 'edit_attachment', 'save_iar_status_field' );

// Смена статуса файла
function iar_switcher($post_id){
	// Получаем текущий статус
	$iar_status = get_post_meta( $post_id, 'iar_status', true );
	// Если статус no
	if ($iar_status == 'no'){
		// Меняем на yes
		update_post_meta( $post_id, 'iar_status', 'yes' );
	}
	// Если любой другой (тут может быть yes или пусто)
	else{
		// Ставим no
		update_post_meta( $post_id, 'iar_status', 'no' );
	}
}

// Функция для смены статуса по кнопке (она сработает только если отключён jQuery)
function switch_iar_status(){
	// Получаем id поста (файла) из GET или POST
	$post_id = (isset($_GET['post']) ? $_GET['post'] : $_POST['post']);
	// Запускаем функцию смены статуса
	iar_switcher($post_id);
	// Возвращаемся на страницу библиотеки файлов
	wp_redirect( admin_url( 'upload.php') );
}
// Привязка к ссылке с собственным action в GET
add_action( 'admin_action_switch_iar_status', 'switch_iar_status' );

// Смена статуса файла (если js работает)
function iar_action_javascript() {
?>
<script type="text/javascript" >
	jQuery(document).ready(function($) {
		$(".iar-switch").click(function(event) {
			event.preventDefault();
			$('body').addClass('iar-wait');
			id = $(this).attr("iar-id");
			var data = {
				action: 'iar_action',
				post_id: id
			};
			$.post(ajaxurl, data, function(response) {
				$('body').removeClass('iar-wait');
				if ( response == 'no' ) {
					$( "#iar-id-"+id ).addClass( 'iar-no' ).removeClass( 'iar-yes' );
				}
				else {
					$( "#iar-id-"+id ).addClass( 'iar-yes' ).removeClass( 'iar-no' );
				}
			});
		});
	});
</script>
<?php
}
// Вызываем скрипт в подвале админки
add_action( 'admin_footer', 'iar_action_javascript' );

// Функция обработки запроса от AJAX в iar_action_javascript()
function iar_action_callback() {
	// Переменная, которую передал AJAX
	$post_id = $_POST['post_id'];
	// Запускаем смену статуса
	iar_switcher($post_id);
	// Получаем новый статус
	$iar_status = get_post_meta( $post_id, 'iar_status', true );
	// Возвращаем в AJAX новый статус
	echo $iar_status;
	// (ЗАЧЕМ ЭТО?)
	die();
}
// AJAX сообщил, какой именно action запустить
add_action( 'wp_ajax_iar_action', 'iar_action_callback' );

?>