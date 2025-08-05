import { Form } from "react-bootstrap";
import { FormGroupTag } from "modules/proposal/style";

export const PanAlternatives = ({
  temp_data,
  owner,
  register,
  allFieldsReadOnly,
}) => {
  const companyAlias = temp_data?.selectedQuote?.companyAlias;
  const isForm49Acceptable =
    temp_data?.selectedQuote?.companyAlias === "royal_sundaram";
    
  return (
    <div className="py-2 fname">
      <FormGroupTag mandatory>{`Form 60${
        companyAlias === "royal_sundaram" ? " / 49A" : ""
      }`}</FormGroupTag>
      <Form.Control
        as="select"
        autoComplete="none"
        size="sm"
        ref={register}
        name="formType"
        readOnly={allFieldsReadOnly}
        className="title_list"
        style={{ cursor: "pointer" }}
      >
        <option style={{ cursor: "pointer" }} value={"form60"} selected={owner}>
          {"Form 60"}
        </option>
        {isForm49Acceptable ? (
          <option style={{ cursor: "pointer" }} value={"form49a"}>
            {"Form 49 A"}
          </option>
        ) : (
          <noscript />
        )}
      </Form.Control>
    </div>
  );
};
