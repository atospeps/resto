<?php
/**
 * Processing cart class.
 * @author: Atos
 */
 class RestoProcessingCart {
    
    /*
     * Context
     */
    public $context;
    
    /*
     * Owner of the processing cart
     */
    public $user;
    
    /*
     * Processing cart items 
     */
    private $items = array();

    /*
     * RestoProcessingCart instance
     */
    private static $_instance = null;

    /**
     * Constructor
     * 
     * @param RestoUser $user
     * @param RestoContext $context
     */
    private function __construct($user, $context)
    {
        $this->user = $user;
        $this->context = $context;
        $this->items = $this->context->dbDriver->get(RestoDatabaseDriver::PROCESSING_CART_ITEMS, array(
            'user' => $this->user,
            'context' => $this->context
        ));
    }

    /**
     *
     * Returns RestoProcessingCart instance (singleton).
     * @param RestoUser $user
     * @param RestoContext $context
     */
    public static function getInstance($user, $context){
        if(is_null(self::$_instance)) {
            self::$_instance = new RestoProcessingCart($user, $context);
        }
        return self::$_instance;
    }

    /**
     * Add items to processing cart
     * 
     * @param array $data
     * 
     * @return array $items les produits réellement ajoutés au panier
     */
    public function add($data)
    {
        if (!is_array($data)) {
            return false;
        }
                    
        $items = array();
        for ($i = count($data); $i--;) {
                    
            if (!isset($data[$i]['id']) || isset($this->items[$data[$i]['id']])) {
                continue;
            }
            
            if (!$this->context->dbDriver->store(RestoDatabaseDriver::PROCESSING_CART_ITEM, array(
                'email'  => $this->user->profile['email'],
                'userid' => $this->user->profile['userid'],
                'item'   => $data[$i]))
            ) {
                return false;
            }
            
            $this->items[$data[$i]['id']] = $data[$i];
            $items[$data[$i]['id']] = $data[$i];
        }
        
        return $items;
    }
    
    /**
     * Update item in processing cart
     * 
     * @param string $itemId
     * @param array $item
     */
    public function update($itemId, $item)
    {
        if (!isset($itemId)) {
            return false;
        }
        if (!isset($this->items[$itemId])) {
            RestoLogUtil::httpError(1001, 'Cannot update item : ' . $itemId . ' does not exist');
        }
        
        $this->items[$itemId] = $item;
        
        return $this->context->dbDriver->update(RestoDatabaseDriver::PROCESSING_CART_ITEM, array(
            'userid' => $this->user->profile['userid'],
            'email'  => $this->user->profile['email'],
            'itemId' => $itemId,
            'item'   => $item
        ));
    }
    
    /**
     * Remove item from processing cart
     * 
     * @param string $itemId
     */
    public function remove($itemId)
    {
        if (!isset($itemId)) {
            return false;
        }
        
        if (isset($this->items[$itemId])) {
            unset($this->items[$itemId]);
        }
        
        return $this->context->dbDriver->remove(RestoDatabaseDriver::PROCESSING_CART_ITEM, array(
                'userid' => $this->user->profile['userid'],
                'email'  => $this->user->profile['email'],
                'itemId' => $itemId
        ));
    }
    
    /**
     * Remove all items from processing cart
     * 
     */
    public function clear()
    {
        $this->items = array();
        return $this->context->dbDriver->remove(RestoDatabaseDriver::PROCESSING_CART_ITEMS, array(
            'userid' => $this->user->profile['userid']
        ));
    }
    
    /**
     * Returns all items from processing cart
     * 
     */
    public function getItems()
    {
        return $this->items;
    }
    
    /**
     * Return the processing cart as a JSON file
     * 
     * @param boolean $pretty
     */
    public function toJSON($pretty)
    {
        $response = array('items' => $this->getItems());
        return RestoUtil::json_format($response, $pretty);
    }

}
