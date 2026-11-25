<?php

return [
    /*
     * Minutos que un trabajo de impresión puede esperar en la cola. Si la impresora o el
     * celular estuvieron desconectados más tiempo, al volver no se imprime esa cola vieja
     * (comandas ya preparadas, tickets de clientes que se fueron): se cancela con un aviso
     * y se puede reimprimir a mano si aún hace falta.
     */
    'vigencia_minutos' => (int) env('IMPRESION_VIGENCIA_MINUTOS', 15),
];
