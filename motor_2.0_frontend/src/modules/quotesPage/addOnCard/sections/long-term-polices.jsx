import React from "react";
import { CardBlock, FilterMenuBoxCheckConatiner } from "../style";

const LongTermPolices = ({
  tab,
  longTerm2,
  setLongterm2,
  longTerm3,
  setLongterm3,
}) => {
  return (
    <CardBlock>
      <>
        <FilterMenuBoxCheckConatiner>
          <div className="filterMenuBoxCheck">
            <input
              type="checkbox"
              className="form-check-input"
              id={"2 years (OD + TP)"}
              value={"2 years (OD + TP)"}
              defaultChecked={longTerm2}
              checked={longTerm2}
              onChange={(e) => {
                setLongterm2((prev) => !prev);
                setLongterm3(false);
              }}
            />
            <label className="form-check-label" htmlFor={"2 years (OD + TP)"}>
              {`2 years (${tab === "tab2" ? `` : `OD + `}TP)`}
            </label>
            <span style={{ marginLeft: "3px" }}></span>
          </div>
        </FilterMenuBoxCheckConatiner>

        <FilterMenuBoxCheckConatiner>
          <div className="filterMenuBoxCheck">
            <input
              type="checkbox"
              className="form-check-input"
              id={"3 years (OD + TP)"}
              value={"3 years (OD + TP)"}
              defaultChecked={longTerm3}
              checked={longTerm3}
              onChange={(e) => {
                setLongterm2(false);
                setLongterm3((prev) => !prev);
              }}
            />
            <label className="form-check-label" htmlFor={"3 years (OD + TP)"}>
              {`3 years (${tab === "tab2" ? `` : `OD + `}TP)`}
            </label>
            <span style={{ marginLeft: "3px" }}></span>
          </div>
        </FilterMenuBoxCheckConatiner>
      </>
    </CardBlock>
  );
};

export default LongTermPolices;
