import React, { useState } from "react";
import { Link } from "react-router-dom";

const Navbar = ({ handleLogout }) => {
  const [isOpen, setIsOpen] = useState(false);

  const toggleNavbar = () => {
    setIsOpen(!isOpen);
  };

  return (
    <nav className="navbar navbar-expand-lg navbar-dark bg-dark w-100 mb-4">
  <div className="container-fluid d-flex justify-content-between align-items-center">
    <Link className="navbar-brand fw-bold" to="/home">InvestZero</Link>
    <button
      className="navbar-toggler"
      type="button"
      onClick={toggleNavbar}
      aria-controls="navbarNav"
      aria-expanded={isOpen}
      aria-label="Toggle navigation"
    >
      <span className="navbar-toggler-icon"></span>
    </button>

    <div className={`collapse navbar-collapse ${isOpen ? "show" : ""}`} id="navbarNav">
      <ul className="navbar-nav me-auto mb-2 mb-lg-0">
        <li className="nav-item">
          <Link className="nav-link" to="/home">Home</Link>
        </li>
        <li className="nav-item">
          <Link className="nav-link" to="/searchallstocks">Stock Search</Link>
        </li>
        <li className="nav-item">
          <Link className="nav-link" to="/chartpage/AAPL">Charts</Link>
        </li>
        <li className="nav-item">
          <Link className="nav-link" to="/news">News</Link>
        </li>
        <li className="nav-item">
          <Link className="nav-link" to="/taxDocument">Tax Document</Link>
        </li>
      </ul>
      <button onClick={handleLogout} className="btn btn-danger">Logout</button>
    </div>
  </div>
</nav>

  );
};

export default Navbar;