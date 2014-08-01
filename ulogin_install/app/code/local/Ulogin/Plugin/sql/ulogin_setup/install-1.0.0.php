<?php
try {
    $installer = $this;
    $installer->startSetup();
    $table = $installer->getConnection()
        ->newTable($installer->getTable('ulogin/account'))
        ->addColumn('account_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ), 'Id')
        ->addColumn('userid', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
            'unsigned'  => true
        ), 'Userid')
        ->addColumn('identity', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
            'nullable'  => false,
        ), 'Identity')
        ->addColumn('network', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
            'nullable'  => true,
        ), 'Network');
    $installer->getConnection()->createTable($table);
    $installer->endSetup();
} catch ( Exception $e ) {
}