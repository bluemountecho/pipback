<?php

function pipback_withdrawals_page() {
    echo '<div class="wrap"><h1>Withdrawal Requests</h1>';
    echo '<form method="post">';
    $table = new Withdrawal_Requests_List_Table();
    $table->prepare_items();
    $table->display();
    echo '</form>';
    echo '</div>';
}
