import React, {useState, useEffect} from "react";
import { Link } from "react-router-dom";
import axios from "axios";


function HomePage({ user, handleLogout }) {

  // useState that runs to retrieve userBalance 
  const [userBalance, setUserBalance] = useState(null)
  const [haveUserBalance, gettingUserBalance] = useState(false)
  const[userInfo, setUserInfo] = useState(null)
  const [error, setError] = useState("");


  useEffect(() => {
    
    const getUserInfo = async () => {
  
      try {
        const response = await axios.post(
          "http://www.sample.com/backend/webserver_backend.php",
          { type: "getAccountInfo"},
          { withCredentials: true } // Send cookies
        );

        // echo json_encode([
        //   "userStocks" => $response['user']['userStocks'],
        //   "userCashBalance" => $response['user']['userBalance']['cashBalance'],
        //   "userStockBalance" => $response['user']['userBalance']['stockBalance'],
        //   "userTotalBalance" => $response['user']['userBalance']['totalBalance'],
        //   "sessionId" => $response['sessionId']
  
        console.log(JSON.stringify(response.data));
  
        if (response.data.success) {
          gettingUserBalance(true)
          setUserBalance(response.data.userTotalBalance)
        } else {
          setError("Unable to get user balance");
        }
      } catch (error) {
        console.error("Error connecting to server:", error);
        setError(error);
      }
    };
    getUserInfo()



  }, [userBalance])
  
  return (
    <div className="homepage container">
      <h1>Welcome to your Home Page</h1>

        <Link to="/transactions">
          <button>View Transactions</button>
        </Link>


      {user ? (
        <div>
          <h3>Logged in as: {user.username}</h3>

          {haveUserBalance ? <h2> Your current balance is : {userBalance} </h2> : <h2> Loading your balance</h2>}
          <button onClick={handleLogout}>Logout</button>


        </div>
      ) : (
        <h3>No user data available...</h3>
      )}
    </div>
  );
}

export default HomePage;
