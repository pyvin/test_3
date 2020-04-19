<?php

class CheckMail
{

  private $emails;
	private $domains;
  private $host = '127.0.0.1';
  private $user = 'root';
  private $password = 'new-password';
  private $db = 'test_date';
  
  const EMAILS_TABLE_NAME = 'users';
	const VALIDATION_TABLE_NAME = 'validation';

    public function __construct(int $limit = 1000)
    {
        $this->connect_db = new PDO("mysql:dbname=$this->db;host=$this->host", $this->user, $this->password);
        $this->limit = $limit;
    }

    /**
     * Получает список писем
     */
    function getEmails()
    {
        $query = $this->connect_db->prepare("SELECT * FROM users LIMIT {$this->limit}");
        
        try {
          $query->execute();
        } catch (Exception $e) {
          //TODO: some logging stuff here
        }
        $this->emails = $query->fetchAll();
    }

    /*
    *  Получаем почтовый домен
    */
    function getDomainName($email): string 
    {
        return end(explode('@', $email));
    }

    /**
     * Проверка доммена
     *
     * @param $email
     * @return bool
     */
    private function domainСheck($email): bool
    {
      $domain = $this->getDomainName($email);
    
      if (!isset($this->domains[$domain])) { 
          $this->domains[$domain] = checkdnsrr($domain); //Проверяет записи DNS
      }
      
      return $this->domains[$domain];
    }


    /**
     * Проверяет, что значение является корректным e-mail
     *
     * @param $email
     * @return bool
     */
    function filterEmail($email): bool
    {
      return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
  
  /**
	 * Checks validation of domain name in address. Uses DNS for checking
	 *
	 * @param $email
	 * @return bool
	 */
	private function validateSMTPResponse($email): bool
	{
      $result = '';
      $domain = substr(strrchr($email, "@"), 1);
      $res = getmxrr($domain, $mx_records, $mx_weight);
      if (false == $res || 0 == count($mx_records) || (1 == count($mx_records) && ($mx_records[0] == null  || $mx_records[0] == "0.0.0.0" ) ) ){
          //Проверка не пройдена - mx-записи не обнаружены
          $result = false;
      } else{
          //Проверка пройдена, почта на нём работает
          $result = true;
      }
		return $result;
	}

    /**
     * Фильтруем почту
     * @param $email
     * @return bool
     */
    private function validateEmail($email): bool
    {
      return $this->filterEmail($email) 
          && $this->domainСheck($email) 
          && $this->validateSMTPResponse($email);
    }
  
  
  /**
	 * Проверка писем
	 */
	private function checkEmails()
	{
      foreach ($this->emails as &$email) {
          $email['is_valid'] = $this->validateEmail($email['email']);
      }
  }
  
  /**
	 * Проводим верификацию статусов
	 */
	private function saveStatus()
	{
    $sql = "INSERT INTO validation (id, email_id, is_valid, created_at) VALUES (?, ?,?,?)";
    $connect = $this->connect_db->prepare($sql);
    

    foreach ($this->emails as $email) {
      if ($email['is_valid']) {
          $connect->execute([NULL, $email['id'], $email['is_valid'], date('Y-m-d H:m:s')]);    
      }
    }
	}

    /**
     * Запускаем
     */
    public function processAll()
    {
      $this->getEmails();
      $this->checkEmails();
      $this->saveStatus();
    }
}


$rd = new CheckMail();
echo $rd->processAll();
