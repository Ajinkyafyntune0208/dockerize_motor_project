/* 
  This component will be used when dropdown won't be toggled by click.
  it accepts two type of values in dropdown(links and plain text)

  hasLink Prop has to be passed when using links in the dropdown values
*/
import React, { useState } from "react";
import "../Header.scss";

const DropdownOnHover = ({ label, items, hasLink }) => {
  let tempKey = 0;

  const [selectedItem, setSelectedItem] = useState(false);

  const handleClick = (data) => {
    setSelectedItem(data);
  };
  return (
    <>
      <span className="dropdown1">
        <span style={{ padding: "43px 0", marginRight: "10px" }}>{label}</span>
        <div className="dropdown-content">
          <ul>
            {items && hasLink
              ? items.map((data) => {
                  return (
                    <li key={tempKey++}>
                      <a
                        className="dropdown-item"
                        href={hasLink ? data?.link : undefined}
                        target="_blank"
                      >
                        {data.name}
                      </a>
                    </li>
                  );
                })
              : items &&
                items.map((data) => {
                  return (
                    <li
                      key={tempKey++}
                      onClick={() => handleClick(data.name || data)}
                    >
                      {data.name || data}
                    </li>
                  );
                })}
          </ul>
        </div>
      </span>
    </>
  );
};

export default DropdownOnHover;
