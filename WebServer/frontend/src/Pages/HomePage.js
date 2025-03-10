import React from "react";

function HomePage({ user, handleLogout }) {

  
  return (
    <div className="homepage container">
      <h1>Welcome to your Home Page</h1>

      {user ? (
        <div>
          <h3>Logged in as: {user.username}</h3>

          <button onClick={handleLogout}>Logout</button>
          <TradingChart stockData={{ticker:"TSLA", name: "Tesla" , marketCap: 12912759190.3, description:"Tesla stock (TSLA) is a stock that represents ownership in Tesla, Inc., a company that designs, manufactures, and sells electric vehicles and energy storage systems.", sector:"Consumer Discretionary"}}></TradingChart>
        </div>
      ) : (
        <h3>No user data available...</h3>
      )}
    </div>
  );
}

export default HomePage;
