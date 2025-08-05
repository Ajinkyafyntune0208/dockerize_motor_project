import React from "react";
import tooltip from "../../../../../assets/img/tooltip.svg";
import { CardBlock, FilterMenuBoxCheckConatiner } from "../../style";
import { CustomTooltip } from "components";

const Cpa = ({
  cpa,
  setCpa,
  setMultiCpa,
  lessthan767,
  type,
  temp_data,
  multiCpa,
  setOnCpaChange
}) => {
  return (
    <CardBlock>
      <>
        <FilterMenuBoxCheckConatiner>
          <div className="filterMenuBoxCheck">
            <input
              type="checkbox"
              className="form-check-input"
              id={"Compulsory Personal Accident"}
              value={"Compulsory Personal Accident"}
              defaultChecked={cpa}
              //value={cpa}
              checked={cpa}
              onChange={(e) => {
                setCpa(!cpa);
                temp_data?.newCar && setOnCpaChange(true)
              }}
              onClick={() => setMultiCpa(false)}
            />

            <CustomTooltip
              rider="true"
              id="cpa1__Tooltipvol"
              place={"right"}
              customClassName="mt-3  "
            >
              <label
                data-tip={
                  !lessthan767 &&
                  "<h3 >Compulsory Personal Accident</h3> <div>Compulsory Personal Accident cover protects you against partial, total disability, or death caused due to an accident. As per the IRDAI notice. Personal Accident (PA) Cover is mandatory if the car is owned by an individual.</div>"
                }
                data-html={!lessthan767 && true}
                data-for={!lessthan767 && "cpa1__Tooltipvol"}
                className="form-check-label"
                htmlFor={"Compulsory Personal Accident"}
              >
                {"Compulsory Personal Accident"}{" "}
              </label>
            </CustomTooltip>

            <span style={{ marginLeft: "3px" }}>
              {lessthan767 ? (
                <CustomTooltip
                  rider="true"
                  id="cpa1__Tooltipvol_m"
                  place={"right"}
                  customClassName="mt-3 "
                  allowClick
                >
                  <img
                    data-tip="<h3 >Compulsory Personal Accident</h3> <div>Compulsory Personal Accident cover protects you against partial, total disability, or death caused due to an accident. As per the IRDAI notice. Personal Accident (PA) Cover is mandatory if the car is owned by an individual.</div>"
                    data-html={true}
                    data-for="cpa1__Tooltipvol_m"
                    src={tooltip}
                    alt="tooltip"
                    // className="toolTipRiderChild"
                  />
                </CustomTooltip>
              ) : (
                <noscript />
              )}
            </span>
          </div>
        </FilterMenuBoxCheckConatiner>
        {type !== "cv" && temp_data?.newCar && (
          <FilterMenuBoxCheckConatiner>
            <div className="filterMenuBoxCheck">
              <input
                type="checkbox"
                className="form-check-input"
                id={"Compulsory Personal Accident 1"}
                value={"Compulsory Personal Accident 1"}
                defaultChecked={multiCpa}
                //value={multiCpa}
                checked={multiCpa}
                onChange={(e) => {
                  setMultiCpa(!multiCpa);
                  temp_data?.newCar && setOnCpaChange(true)
                }}
                onClick={() => setCpa(false)}
              />

              <CustomTooltip
                rider="true"
                id="cpa1__Tooltipvol"
                place={"right"}
                customClassName="mt-3  "
              >
                <label
                  data-tip={
                    !lessthan767 &&
                    "<h3 >Compulsory Personal Accident</h3> <div>Compulsory Personal Accident cover protects you against partial, total disability, or death caused due to an accident. As per the IRDAI notice. Personal Accident (PA) Cover is mandatory if the car is owned by an individual.</div>"
                  }
                  data-html={!lessthan767 && true}
                  data-for={!lessthan767 && "cpa1__Tooltipvol"}
                  className="form-check-label"
                  htmlFor={"Compulsory Personal Accident 1"}
                >
                  {`Compulsory Personal Accident (${
                    type === "car" ? "3" : "5"
                  } years)`}{" "}
                </label>
              </CustomTooltip>

              <span style={{ marginLeft: "3px" }}>
                {lessthan767 ? (
                  <CustomTooltip
                    rider="true"
                    id="cpa1__Tooltipvol_m"
                    place={"right"}
                    customClassName="mt-3 "
                    allowClick
                  >
                    <img
                      data-tip="<h3 >Compulsory Personal Accident</h3> <div>Compulsory Personal Accident cover protects you against partial, total disability, or death caused due to an accident. As per the IRDAI notice. Personal Accident (PA) Cover is mandatory if the car is owned by an individual.</div>"
                      data-html={true}
                      data-for="cpa1__Tooltipvol_m"
                      src={tooltip}
                      alt="tooltip"
                    />
                  </CustomTooltip>
                ) : (
                  <noscript />
                )}
              </span>
            </div>
          </FilterMenuBoxCheckConatiner>
        )}
      </>
    </CardBlock>
  );
};

export default Cpa;
