<?php
/*
 * Plugin Name: Easy Digital Downloads Addon for WP Courseware
 * Version: 1.0
 * Plugin URI: http://flyplugins.com
 * Description: The official extension for WP Courseware to add integration for Easy Digital Downloads.
 * Author: Fly Plugins
 * Author URI: http://flyplugins.com
 */



// Main parent class
include_once 'class_members.inc.php';

// Hook to load the class
add_action('init', 'WPCW_EDD_init',1);


/**
 * Initialize the membership plugin, only loaded if WP Courseware 
 * exists and is loading correctly.
 */
function WPCW_EDD_init()
{
	$item = new WPCW_EDD();
	
	// Check for WP Courseware
	if (!$item->found_wpcourseware()) {
		$item->attach_showWPCWNotDetectedMessage();
		return;
	}
	
	// Not found the membership tool
	if (!$item->found_membershipTool()) {
		$item->attach_showToolNotDetectedMessage();
		return;
	}
	
	// Found the tool and WP Coursewar, attach.
	$item->attachToTools();
}


/**
 * Membership class that handles the specifics of the Easy Digital Downloads plugin and
 * handling the data for products for that plugin.
 */
class WPCW_EDD extends WPCW_Members
{
	const GLUE_VERSION  	= 1.00; 
	const EXTENSION_NAME 	= 'Easy Digital Downloads';
	const EXTENSION_ID 		= 'WPCW_EDD';
	
	
	
	/**
	 * Main constructor for this class.
	 */
	function __construct()
	{
		// Initialize using the parent constructor 
		parent::__construct(WPCW_EDD::EXTENSION_NAME, WPCW_EDD::EXTENSION_ID, WPCW_EDD::GLUE_VERSION);
	}
	
	
	
	/**
	 * Get a list of EDD products.
	 */
	protected function getMembershipLevels()
	{
		//Get all published download posts
        $levelData = get_posts( array( 'post_type' => 'download', 'posts_per_page' => -1 ) );

		if ($levelData && count($levelData) > 0)
		{
			$levelDataStructured = array();
			
			// Format the data in a way that we expect and can process
			foreach ($levelData as $levelDatum)
			{
				$levelItem = array();
				$levelItem['name'] 	= get_the_title($levelDatum->ID) ;
				$levelItem['id'] 	= $levelDatum->ID;
				$levelDataStructured[$levelItem['id']]  = $levelItem;
			}
			
			return $levelDataStructured;
		}
		
		return false;
	}

	
	/**
	 * Function called to attach hooks for handling when a user is updated or created.
	 */	
	protected function attach_updateUserCourseAccess()
	{
        	add_action( 'edd_updated_edited_purchase', array( $this, 'handle_updateUserCourseAccess' ));
    		add_action( 'edd_complete_purchase', array( $this, 'handle_updateUserCourseAccess'));
	}


	/**
		 * Assign selected courses to members of a paticular level.
		 * @param Level ID in which members will get courses enrollment adjusted.
		 */
	protected function retroactive_assignment($level_ID)
    {
        //Get all transactions from EDD
        $logging = new EDD_Logging();
        $transactions = $logging->get_logs( $level_ID );

        $payment_ids = array();
        $customer_ids = array();

        //Convert log entries into payment IDs
        if( $transactions ) {
            foreach($transactions as $key => $transaction){
                $payment_ids[] = get_post_meta( $transaction->ID, '_edd_log_payment_id', true );
            }
        }

        //Get IDs that have purchased product
        if( count( $payment_ids ) > 0 ) {
            foreach($payment_ids as $key => $payment_id){
                $customer_ids[$key] = get_post_meta( $payment_id, '_edd_payment_user_id', true );
            }
        }

        //Clean up duplicate IDs
        $customer_ids = array_unique($customer_ids);
		
        $page = new PageBuilder(false);

        //Enroll members of product
        if( count( $customer_ids ) > 0 ) {
            foreach ($customer_ids as $customer_id )
            {
            	$memberLevels = edd_get_users_purchased_products( $customer_id );
            
                $userLevels = array();

				foreach( $memberLevels as $key => $memberLevel ) {
					$userLevels[$key] = $memberLevel->ID;
				}
    
                // Over to the parent class to handle the sync of data.
                parent::handle_courseSync($customer_id, $userLevels);

            }
            $page->showMessage(__('All members were successfully retroactively enrolled into the selected courses.', 'wp_courseware'));
            return;
        } else {
            $page->showMessage(__('No existing members found for the specified level.', 'wp_courseware'));
        }
	}
	

	/**
	 * Function just for handling the membership callback, to interpret the parameters
	 * for the class to take over.
	 * 
	 * @param Integer $id The ID if the user being changed.
	 * @param Array $levels The list of levels for the user.
	 */
	public function handle_updateUserCourseAccess($transaction_id)
    {
        // Get transaction data
        $transaction = get_post( $transaction_id );
        
        // Get customer ID based on transaction ID
        $customer_id = get_post_meta( $transaction->ID, '_edd_payment_user_id', true );
        
        // Get all membership levels for customer making current transaction
		$memberLevels = edd_get_users_purchased_products( $customer_id );

		$userLevels = array();

			foreach( $memberLevels as $key => $memberLevel ) {
				$userLevels[$key] = $memberLevel->ID;
			}
		// Over to the parent class to handle the sync of data.
		parent::handle_courseSync($customer_id, $userLevels);
	}
	

	/**
	 * Detect presence of the Easy Digital Downloads plugin.
	 */
	public function found_membershipTool()
	{
		return class_exists('Easy_Digital_Downloads');
	}
	
	
}
