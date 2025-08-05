import React from "react";
import { CardBlock, FilterMenuBoxCheckConatiner } from "../../style";

const PlanTypes = ({
  annualCompPolicy,
  setShortCompPolicy3,
  setAnnualCompPolicy,
  setShortCompPolicy6,
  theme_conf,
  shortCompPolicy3,
  shortCompPolicy6,
  sortBy,
  setSortBy,
}) => {
  return (
    <CardBlock>
      <>
        <FilterMenuBoxCheckConatiner>
          <div className="filterMenuBoxCheck">
            <input
              type="checkbox"
              className="form-check-input"
              id={"Annual Policy (1 yr OD + 1 yr TP)"}
              value={"Annual Policy (1 yr OD + 1 yr TP)"}
              defaultChecked={annualCompPolicy}
              //value={cpa}
              checked={annualCompPolicy}
              onChange={(e) => {
                setShortCompPolicy3(false);
                setAnnualCompPolicy(true);
                setShortCompPolicy6(false);
              }}
            />

            <label
              className="form-check-label"
              htmlFor={"Annual Policy (1 yr OD + 1 yr TP)"}
            >
              {"Annual Policy (1 yr OD + 1 yr TP)"}{" "}
            </label>

            <span style={{ marginLeft: "3px" }}></span>
          </div>
        </FilterMenuBoxCheckConatiner>
        <FilterMenuBoxCheckConatiner
          hide={theme_conf?.broker_config?.threeMonthShortTermEnable !== "yes"}
        >
          <div className="filterMenuBoxCheck">
            <input
              type="checkbox"
              className="form-check-input"
              id={"Short Term Policy (3 months)"}
              value={"Short Term Policy (3 months)"}
              defaultChecked={shortCompPolicy3}
              //value={cpa}
              checked={shortCompPolicy3}
              onChange={(e) => {
                setShortCompPolicy3(true);
                setShortCompPolicy6(false);
                setAnnualCompPolicy(false);
                //temp fix - to be removed later
                sortBy && setTimeout(() => setSortBy(sortBy), 200);
              }}
            />
            <label
              className="form-check-label"
              htmlFor={"Short Term Policy (3 months)"}
            >
              {"Short Term Policy (3 months)"}{" "}
            </label>

            <span style={{ marginLeft: "3px" }}></span>
          </div>
        </FilterMenuBoxCheckConatiner>
        <FilterMenuBoxCheckConatiner
          hide={theme_conf?.broker_config?.sixMonthShortTermEnable !== "yes"}
        >
          <div className="filterMenuBoxCheck">
            <input
              type="checkbox"
              className="form-check-input"
              id={"Short Term Policy (6 months)"}
              value={"Short Term Policy (6 months)"}
              defaultChecked={shortCompPolicy6}
              //value={cpa}
              checked={shortCompPolicy6}
              onChange={(e) => {
                setShortCompPolicy6(true);
                setShortCompPolicy3(false);
                setAnnualCompPolicy(false);
                //temp fix - to be removed later
                sortBy && setTimeout(() => setSortBy(sortBy), 200);
              }}
            />

            <label
              className="form-check-label"
              htmlFor={"Short Term Policy (6 months)"}
            >
              {"Short Term Policy (6 months)"}{" "}
            </label>

            <span style={{ marginLeft: "3px" }}></span>
          </div>
        </FilterMenuBoxCheckConatiner>
      </>
    </CardBlock>
  );
};

export default PlanTypes;
