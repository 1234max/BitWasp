<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Users Model
 *
 * This class handles the database queries relating to users.
 * 
 * @package		BitWasp
 * @subpackage	Models
 * @category	Users
 * @author		BitWasp
 * 
 */

class Users_model extends CI_Model {
	
	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */	
	public function __construct(){	}

	/**
	 * Add User.
	 * 
	 * Add a user to the table. Use prepared statements..
	 *
	 * @access	public
	 * @param	array
	 * @param	string
	 * @return	bool
	 */					
	public function add($data, $token_info = NULL) {
		$sql = "INSERT INTO bw_users (user_name, password, salt, user_hash, user_role, register_time, public_key, private_key, location) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		$query = $this->db->query($sql, array($data['user_name'],$data['password'],$data['salt'], $data['user_hash'], $data['user_role'], time(), $data['public_key'], $data['private_key'], $data['location'])); 
		if($query){
			if($token_info !== FALSE)			$this->delete_registration_token($token_info['id']);
			
			return TRUE;
		} 
		return FALSE;
		
	}
	
	/**
	 * Remove
	 * 
	 * Remove a user. Haven't had to code this yet..
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */				
	public function remove($user_hash) {}
	
	// Load a users information, by hash/name/id.
	/**
	 * Get
	 * 
	 * Get a user, based on $user['user_hash'], $user['id'], $user['user_name']
	 *
	 * @access	public
	 * @param	array
	 * @return	array / FALSE
	 */					
	public function get(array $user) {

		if (isset($user['user_hash'])) {
			// Duplicate the select statement to prevent weird errors later on.
			$this->db->select('id, banned, user_hash, user_name, local_currency, user_role, salt, force_pgp_messages, two_factor_auth');			
			$query = $this->db->get_where('users', array('user_hash' => $user['user_hash']));
		} elseif (isset($user['id'])) {
			$this->db->select('id, banned, user_hash, user_name, local_currency, user_role, salt, force_pgp_messages, two_factor_auth');
			$query = $this->db->get_where('users', array('id' => $user['id']));
		} elseif (isset($user['user_name'])) {
			$this->db->select('id, banned, user_hash, user_name, local_currency, user_role, salt, force_pgp_messages, two_factor_auth');			
			$query = $this->db->get_where('users', array('user_name' => $user['user_name']));
		} else {
			return FALSE; //No suitable field found.
		}
		
		return ($query->num_rows() > 0) ? $query->row_array() : FALSE;
	}
	
	// Load a users RSA public and pw-protected private key.
	/**
	 * Message Data
	 * 
	 * Load information regarding the users RSA encryption keys.
	 *
	 * @access	public
	 * @param	array
	 * @return	array / FALSE
	 */					
	public function message_data(array $user) {
		$this->db->select('public_key, private_key, salt');

		if (isset($user['user_hash'])) {
			$query = $this->db->get_where('users', array('user_hash' => $user['user_hash']));
		} elseif (isset($user['id'])) {
			$query = $this->db->get_where('users', array('id' => $user['id']));
		} elseif (isset($user['user_name'])) {
			$query = $this->db->get_where('users', array('user_name' => $user['user_name']));
		} else {
			return FALSE; //No suitable field found.
		}

		if($query->num_rows() > 0) {
			$row = $query->row_array();
			$pubkey = base64_decode($row['public_key']);
			$privkey = base64_decode($row['private_key']);
			
			$results = array('salt' => $row['salt'],
							 'public_key' => $pubkey,
							 'private_key' => $privkey);
			return $results;
		}
			
		return FALSE;
	}
	
	// Return valid data when a users username, password, salt are specified. 
	/**
	 * Check Password.
	 * 
	 * Returns userdata when a users username, password and salt are entered correctly.
	 *
	 * @access	public
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return	a
	 */					
	public function check_password($user_name, $salt, $password){
		$this->db->select('id')
				 ->where('user_name',$user_name)
				 ->where('password', $this->general->hash($password, $salt));
		$query = $this->db->get('users');
		
		return ($query->num_rows() > 0) ? $query->row_array() : FALSE;
	}
	
	/**
	 * Add Registration Token
	 * 
	 * Add an array describing a registration token.
	 *
	 * @access	public
	 * @param	string
	 * @return	bool
	 */					
	public function add_registration_token($token) {
		if($this->db->insert('registration_tokens', $token) == TRUE)
			return TRUE;
		return FALSE;
	}
	
	/**
	 * List Registration Tokens
	 * 
	 * This function loads a list of the current registration tokens 
	 * on record.
	 * 
	 * @return		array
	 */
	public function list_registration_tokens() {
		$query = $this->db->get('registration_tokens');
		if($query->num_rows() > 0)
			return $query->result_array();
			
		return FALSE;
	}
	
	/**
	 * Check Registration Token
	 * 
	 * This function checks whether a registration token is valid or now.
	 * Returns info about the token on success, FALSE on failure.
	 * 
	 * @return	array/FALSE
	 */
	public function check_registration_token($token) {
		
		$this->db->select('id, user_type, token_content');
		$query = $this->db->get_where('registration_tokens', array('token_content' => $token));
		
		if($query->num_rows() > 0){
			$info = $query->row_array();
			$info['user_type'] = array( 'int' => $info['user_type'],
										'txt' => $this->general->role_from_id($info['user_type']));
			
			return $info;
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Delete Registration Token
	 * 
	 * Delete a registration token as specified by $id.
	 * 
	 * @param	int	$id
	 * @return	bool
	 */
	public function delete_registration_token($id) {
		return ($this->db->delete('registration_tokens', array('id' => $id)) == TRUE) ? TRUE : FALSE;
	}
	
	/**
	 * Set Login
	 * 
	 * Set the users login time (user specified by $id)
	 * @param	int $id
	 * @return	bool
	 */
	public function set_login($id) {
		$change = array('login_time' => time());
		
		$this->db->where('id', $id);
		$query = $this->db->update('users', $change);
		return ($query) ? TRUE : FALSE;
	}
};

/* End of File: Users_Model.php */
