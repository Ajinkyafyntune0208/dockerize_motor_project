import React from "react";
import { ToggleContainer, ToggleSwitch } from "../style";
import { BiInfoCircle } from "react-icons/bi";
import { CustomTooltip } from "components";

const Filter = ({
  lessthan767,
  Theme1,
  isRelevant,
  handleRelevantPolicy,
  temp_data,
  renewalFilter,
  handleRenewalFilter,
}) => {
  return (
    <ToggleContainer lessthan767={lessthan767}>
      <ToggleSwitch
        theme1={Theme1}
        type="switch"
        id="custom-switch"
        label={
          <span className="label-text">
            Show Best Match{" "}
            <span>
              <CustomTooltip
                rider="true"
                id="showbestmatch"
                place="right"
                customClassName="mt-1"
              >
                <span
                  data-tip={
                    !lessthan767 &&
                    `<h3>Show Best Match</h3><div>Helps you choose the best-fit plans by highlighting those with full add-on compatibility.</div>`
                  }
                  data-html={!lessthan767}
                  data-for={!lessthan767 && "showbestmatch"}
                  className="cursor-pointer"
                >
                  <BiInfoCircle />
                </span>
              </CustomTooltip>
            </span>
          </span>
        }
        className="toggleBtn"
        checked={isRelevant}
        onChange={handleRelevantPolicy}
      />
      {/* Show Renewal Only Toggle */}
      {temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" &&
        !temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
        import.meta.env.VITE_BROKER === "BAJAJ" &&
        !(
          import.meta.env.VITE_BROKER === "BAJAJ" &&
          import.meta.env.VITE_BASENAME === "general-insurance"
        ) && (
          <ToggleSwitch
            theme1={Theme1}
            type="switch"
            id="custom-switch-1"
            label={<span className="label-text">Show Renewal Only</span>}
            checked={renewalFilter}
            className="toggleBtn-noborder"
            onChange={handleRenewalFilter}
            noBorder
            defaultChecked={
              temp_data?.corporateVehiclesQuoteRequest?.isRenewal === "Y" &&
              !temp_data?.corporateVehiclesQuoteRequest?.frontendTags &&
              import.meta.env.VITE_BROKER === "BAJAJ" &&
              !(
                import.meta.env.VITE_BROKER === "BAJAJ" &&
                import.meta.env.VITE_BASENAME === "general-insurance"
              ) &&
              true
            }
          />
        )}
    </ToggleContainer>
  );
};

export default Filter;
