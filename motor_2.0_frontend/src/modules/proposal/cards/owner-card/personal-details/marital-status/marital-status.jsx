import { useEffect, useState } from "react";
import { Col, ToggleButton } from "react-bootstrap";
import { FormGroupTag, ButtonGroupTag } from "modules/proposal/style";
import _ from "lodash";
import { ErrorMsg } from "components";

export const MaritalStatus = ({
  temp_data,
  owner,
  CardData,
  fields,
  watch,
  register,
  errors,
  allFieldsReadOnly,
}) => {
  const maritalStatus = [
    { name: "Single", value: "1" },
    { name: "Married", value: "2" },
  ];

  const [radioValue2, setRadioValue2] = useState(
    watch("maritalStatus") ||
      (import.meta.env.VITE_BROKER === "OLA" ? "Single" : "Married")
  );

  const MaritalIP = watch("maritalStatus");

  useEffect(() => {
    setTimeout(() => {
      if (
        _.isEmpty(owner) &&
        _.isEmpty(CardData?.owner) &&
        !MaritalIP &&
        !temp_data?.userProposal?.maritalStatus &&
        !_.isEmpty(temp_data)
      ) {
        !MaritalIP &&
          !temp_data?.userProposal?.maritalStatus &&
          (import.meta.env.VITE_BROKER === "OLA"
            ? setRadioValue2("Single")
            : setRadioValue2("Married"));
      }
      if (
        _.isEmpty(owner) &&
        _.isEmpty(CardData?.owner) &&
        !!MaritalIP &&
        temp_data?.userProposal?.maritalStatus
      ) {
        setRadioValue2(temp_data?.userProposal?.maritalStatus);
      }
    }, 500);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [CardData?.owner, owner]);

  //prefill Api
  useEffect(() => {
    if (_.isEmpty(owner) && !_.isEmpty(CardData?.owner)) {
      CardData?.owner?.maritalStatus &&
        setRadioValue2(CardData?.owner?.maritalStatus);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [CardData.owner]);

  return (
    <>
      {fields.includes("maritalStatus") &&
        Number(temp_data?.ownerTypeId) === 1 && (
          <Col xs={12} sm={12} md={12} lg={6} xl={4} className="">
            <FormGroupTag style={{ paddingTop: "10px" }} mandatory>
              Marital Status
            </FormGroupTag>
            <div className="" style={{ width: "100%", paddingTop: "2px" }}>
              <ButtonGroupTag toggle style={{ width: "100%" }}>
                {maritalStatus.map((item, idx) => (
                  <ToggleButton
                    style={{
                      minWidth: "fill-available",
                      width: "fill-available",
                      minHeight: "32px",
                    }}
                    key={idx}
                    className={item.value === "1" ? "mb-2 mr-4" : "mb-2"}
                    type="radio"
                    variant="secondary"
                    ref={register}
                    readOnly={allFieldsReadOnly}
                    size="sm"
                    name="maritalS"
                    tabIndex={"0"}
                    id={`index-key2${idx}`}
                    onKeyDown={(e) => {
                      if (e.keyCode === 32 && !allFieldsReadOnly) {
                        e.preventDefault();
                        document.getElementById(`index-key2${idx}`) &&
                          document.getElementById(`index-key2${idx}`).click();
                      }
                    }}
                    value={item.name}
                    checked={radioValue2 === item.name}
                    onChange={(e) => {
                      !allFieldsReadOnly && setRadioValue2(e.target.value);
                    }}
                  >
                    {item.name}
                  </ToggleButton>
                ))}
              </ButtonGroupTag>
            </div>
            <input
              type="hidden"
              name="maritalStatus"
              value={radioValue2}
              ref={register}
            />
            {!!errors?.maritalStatus && (
              <ErrorMsg fontSize={"12px"} style={{ marginTop: "-3px" }}>
                {errors?.maritalStatus.message}
              </ErrorMsg>
            )}
          </Col>
        )}
    </>
  );
};
