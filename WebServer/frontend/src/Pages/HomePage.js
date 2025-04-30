import React, { useState, useEffect } from "react";
import axios from "axios";
import Navbar from "../Components/Navbar";
import getBackendURL from "../Utils/backendURL";
import Portfolio from "../Components/Portfolio";

function HomePage({ user, handleLogout }) {
  const [userBalance, setUserBalance] = useState(null);
  const [haveUserBalance, setHaveUserBalance] = useState(false);
  const [account, setAccount] = useState(null);
  const [error, setError] = useState("");

  useEffect(() => {
    const getUserInfo = async () => {
      try {
        const response = await axios.post(
          getBackendURL(),
          { type: "GET_ACCOUNT_INFO" },
          { withCredentials: true }
        );

        if (response.status === 200 && response.data) {
          setHaveUserBalance(true);
          setAccount(response.data);

          if (response.data.user && response.data.user.balance !== undefined) {
            setUserBalance(response.data.user.balance);
          } else {
            setUserBalance(0);
          }
        } else {
          setError("Unable to retrieve user balance.");
        }
      } catch (error) {
        console.error("Error fetching user info:", error);
        setError(error.message);
      }
    };

    getUserInfo();
  }, []);

  return (
    <div className="homepage" >
      <Navbar handleLogout={handleLogout}/>

      <div className="row justify-content-center mb-4">
        <div className="col-12 col-md-10 text-center">
          <h1 className="mb-3">Welcome to your Portfolio</h1>
          {user && (
            <h5 className="text-light mb-3">
            Logged in as: <span className="font-weight-bold" style={{color:"lightblue"}}>{user.username}</span>
        
          </h5>
          
          )}
        </div>
      </div>

      <div className="row justify-content-center">
        <div className="col-12 col-md-10">
          {user ? (
            account ? (
              <Portfolio userAccount={account.user} />
            ) : (
              <div className="d-flex justify-content-center py-4">
                <div className="spinner-border text-secondary" role="status">
                  <span className="visually-hidden">Loading...</span>
                </div>
              </div>
            )
          ) : (
            <div className="text-danger text-center mb-4">
              <h4>No user data available...</h4>
            </div>
          )}

          {error && (
            <div className="alert alert-danger text-center mt-4" role="alert">
              Error: {error}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

export default HomePage;
