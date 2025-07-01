<?php

function pipback_cashback_page() {
    echo '<div class="wrap"><h1>Cashback Requests</h1>';
    echo '<form method="post">';
    $table = new Cashback_Requests_List_Table();
    $table->prepare_items();
    echo '<input type="hidden" name="page" value="' . esc_attr($_REQUEST['page'] ?? '') . '" />';
    $table->display();
    echo '</form>';
    echo '</div>';
}
