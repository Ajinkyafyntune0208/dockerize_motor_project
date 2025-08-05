import React from "react";
import { BiError } from "react-icons/bi";

const InternalServerErrorPage = () => {
  return (
    <div>
      <div className="container">
        <BiError className="error-500-className" />
        <h1 className="header">500 ERROR</h1>
        <div className="instructions">
          <h2>
            Sorry, something went wrong on our end. We are currently trying to
            fix the problem.
          </h2>
        </div>
      </div>
    </div>
  );
};

export default InternalServerErrorPage;
