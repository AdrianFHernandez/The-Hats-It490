import React, { useState, useEffect } from "react";
import { Link } from "react-router-dom";
import axios from "axios";
import TradingChart from "../Components/TradingChart";
import getBackendURL from "../Utils/backendURL";
import Portfolio from "../Components/Portfolio";

function HomePage({ user, handleLogout }) {

  // useState that runs to retrieve userBalance 
  const [userBalance, setUserBalance] = useState(null)
  const [haveUserBalance, gettingUserBalance] = useState(false)
  const [account, setAccount] = useState(null)
  const [error, setError] = useState("");

  useEffect(() => {
    const getUserInfo = async () => {
      try {
        const response = await axios.post(
          getBackendURL(),
          { type: "GET_ACCOUNT_INFO" },
          { withCredentials: true } // Send cookies for session authentication
        );

        console.log(JSON.stringify(response.data));
  
        if (response.data) {
          gettingUserBalance(true)
          // setUserBalance(response.data.userTotalBalance)
          setAccount(response.data)
        } else {
          setError("Unable to get user balance");
        }
      } catch (error) {
        console.error("Error fetching user info:", error);
        setError(error.message);
      }
    };

    getUserInfo();
  }, []);


  
  return (
    <div className="homepage container">
      <h1>Welcome to your Home Page</h1>

      <Link to="/searchallstocks">
        <button>Search for stocks</button>
      </Link>

      {user ? (
        <div>
          <h3>Logged in as: {user.username}</h3>

          <button onClick={handleLogout}>Logout</button>
          <TradingChart Ticker={"TSLA"}></TradingChart>
          {/* {account && console.log("account", account.user)} */}
          {account ? <Portfolio userAccount={account.user} ></Portfolio> : <p>Loading portfolio...</p>}
          {haveUserBalance ? <h2> Your current balance is : {userBalance} </h2> : <h2> Loading your balance</h2>}
          <button onClick={handleLogout}>Logout</button>
        </div>
      ) : (
        <h3>No user data available...</h3>
      )}

      {/* Show error message if any */}
      {error && <p style={{ color: "red" }}>Error: {error}</p>}
    </div>
  );
}

export default HomePage;
